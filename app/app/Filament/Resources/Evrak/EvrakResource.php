<?php

namespace App\Filament\Resources\Evrak;

use App\Filament\Resources\Evrak\Pages;
use App\Models\LegacyCustomer;
use App\Services\Identity\IdentityVerificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EvrakResource extends Resource
{
    protected static ?string $model = LegacyCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'evrak';

    protected static ?string $navigationLabel = 'Evraklar';

    protected static ?string $modelLabel = 'Evrak';

    protected static ?string $pluralModelLabel = 'Evrak';

    protected static ?int $navigationSort = 5;

    // Sol menüde 'Evraklar' başlığı altında 3 alt öğe (durum bazlı) göster.
    public static function getNavigationItems(): array
    {
        $items = [
            ['durum' => 'bekleyen', 'label' => 'Bekleyen Evraklar', 'icon' => 'heroicon-o-clock'],
            ['durum' => 'eksik', 'label' => 'Eksik Evraklar', 'icon' => 'heroicon-o-exclamation-triangle'],
            ['durum' => 'tamamlanan', 'label' => 'Tamamlanan Evraklar', 'icon' => 'heroicon-o-check-circle'],
        ];

        $sort = 0;

        return array_map(function (array $it) use (&$sort): NavigationItem {
            $sort++;

            return NavigationItem::make($it['label'])
                ->group('Evraklar')
                ->icon($it['icon'])
                ->sort($sort)
                ->url(static::getUrl('index', ['durum' => $it['durum']]))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.index')
                    && request()->query('durum', 'bekleyen') === $it['durum']);
        }, $items);
    }

    // ── Null-safe evrak SQL'i (JSON_LENGTH(json_null)=1 tuzağına düşmez) ──
    public static function hasDocSql(string $key): string
    {
        return "(COALESCE(JSON_TYPE(JSON_EXTRACT(documents, '$.\"{$key}\"')), 'NULL') <> 'NULL' AND COALESCE(JSON_LENGTH(documents, '$.\"{$key}\"'), 0) > 0)";
    }

    public static function anyDocSql(): string
    {
        $keys = ['identity_front', 'identity_back', 'tax_certificate', 'signature_circular', 'contract', 'other'];

        return '('.implode(' OR ', array_map(fn (string $k): string => self::hasDocSql($k), $keys)).')';
    }

    public static function completeSql(): string
    {
        $company = implode(' AND ', array_map(fn (string $k): string => self::hasDocSql($k), ['identity_front', 'identity_back', 'tax_certificate', 'signature_circular', 'contract']));
        $individual = implode(' AND ', array_map(fn (string $k): string => self::hasDocSql($k), ['identity_front', 'identity_back', 'contract']));

        return "(CASE WHEN customer_type = 'company' THEN ({$company}) ELSE ({$individual}) END)";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Müşteri')->weight('semibold')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('pppoe_username')->label('PPPoE')->searchable()->toggleable(),
                TextColumn::make('customer_type')->label('Tip')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'company' ? 'Kurumsal' : 'Bireysel')
                    ->color(fn (string $state): string => $state === 'company' ? 'warning' : 'info'),
                TextColumn::make('phone')->label('Telefon')->placeholder('—')->searchable(),
                TextColumn::make('kimlik')->label('Kimlik')->badge()
                    ->state(fn (LegacyCustomer $r): string => $r->hasDocument('identity_front') && $r->hasDocument('identity_back') ? 'Var' : 'Eksik')
                    ->color(fn (string $state): string => $state === 'Var' ? 'success' : 'gray'),
                TextColumn::make('sozlesme')->label('Sözleşme')->badge()
                    ->state(fn (LegacyCustomer $r): string => $r->hasDocument('contract') ? 'Var' : 'Eksik')
                    ->color(fn (string $state): string => $state === 'Var' ? 'success' : 'gray'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('uploadDocs')
                    ->label('Evrak Yükle')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->modalHeading(fn (LegacyCustomer $r): string => ($r->name ?: $r->pppoe_username).' — evraklar')
                    ->modalWidth('3xl')
                    ->fillForm(fn (LegacyCustomer $r): array => ['documents' => $r->documents ?? []])
                    ->schema([
                        self::docUpload('documents.identity_front', 'Kimlik Ön Yüz'),
                        self::docUpload('documents.identity_back', 'Kimlik Arka Yüz'),
                        self::docUpload('documents.tax_certificate', 'Vergi Levhası')
                            ->visible(fn (LegacyCustomer $r): bool => $r->customer_type === 'company'),
                        self::docUpload('documents.signature_circular', 'İmza Sirküsü')
                            ->visible(fn (LegacyCustomer $r): bool => $r->customer_type === 'company'),
                        self::docUpload('documents.contract', 'Sözleşme', 2),
                        self::docUpload('documents.other', 'Diğer Evraklar', 0)->columnSpanFull(),
                    ])
                    ->action(function (LegacyCustomer $r, array $data): void {
                        $documents = $data['documents'] ?? [];

                        // Kimlik ön+arka yüklenmişse OCR ile karta göre doğrula:
                        // yanlış kişinin kimliği yüklenmesin. Kurumsalda yetkilinin
                        // kimliği (yetkili TC/ad/soyad) esas alınır.
                        $isCompany = $r->customer_type === 'company';
                        $cardTc = (string) ($isCompany ? $r->authorized_national_id : $r->national_id);
                        $cardFirst = (string) ($isCompany ? $r->authorized_first_name : ($r->first_name ?: ''));
                        $cardLast = (string) ($isCompany ? $r->authorized_last_name : ($r->last_name ?: $r->name));

                        $verification = app(IdentityVerificationService::class)->verifyAgainstCard(
                            $documents['identity_front'] ?? null,
                            $documents['identity_back'] ?? null,
                            $cardTc,
                            $cardFirst,
                            $cardLast,
                        );

                        if ($verification['blocked']) {
                            Notification::make()
                                ->title('Kimlik doğrulanamadı — evrak kaydedilmedi')
                                ->body($verification['reason'])
                                ->danger()
                                ->persistent()
                                ->send();

                            throw new Halt();
                        }

                        $r->update(['documents' => $documents]);

                        $suffix = $verification['engine_down']
                            ? ' (OCR motoru kapalı — kimlik doğrulaması atlandı)'
                            : '';

                        Notification::make()
                            ->title('Evraklar kaydedildi')
                            ->body(($r->name ?: $r->pppoe_username).' — evrak durumu güncellendi.'.$suffix)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function docUpload(string $name, string $label, int $maxFiles = 1): FileUpload
    {
        $u = FileUpload::make($name)
            ->label($label)
            ->disk('local')
            ->directory('customer-documents')
            ->visibility('private')
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(10240)
            ->downloadable();

        if ($maxFiles !== 1) {
            $u->multiple()->appendFiles();
            if ($maxFiles > 1) {
                $u->maxFiles($maxFiles);
            }
        }

        return $u;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvrak::route('/'),
        ];
    }
}
