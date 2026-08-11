<?php

namespace App\Filament\Resources\Evrak;

use App\Filament\Resources\Evrak\Pages;
use App\Models\LegacyCustomer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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

    // UploadEvrak sayfasının form bileşenleri (müşteri tipine göre).
    public static function documentComponents(LegacyCustomer $record): array
    {
        $isCompany = $record->customer_type === 'company';

        // Kimlik/vergi levhası yüklenince ANINDA (kaydetmeden) doğrula → alttaki kutu.
        $verify = fn ($livewire) => method_exists($livewire, 'verifyDocuments') ? $livewire->verifyDocuments() : null;

        return array_merge(
            [
                self::docUpload('documents.identity_front', 'Kimlik Ön Yüz')->live()->afterStateUpdated($verify),
                self::docUpload('documents.identity_back', 'Kimlik Arka Yüz')->live()->afterStateUpdated($verify),
            ],
            $isCompany ? [
                self::docUpload('documents.tax_certificate', 'Vergi Levhası')->live()->afterStateUpdated($verify),
                self::docUpload('documents.signature_circular', 'İmza Sirküsü'),
            ] : [],
            [
                self::docUpload('documents.contract', 'Sözleşme', 2),
                self::docUpload('documents.other', 'Diğer Evraklar', 0)->columnSpanFull(),
                Placeholder::make('evrak_uyari')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->visible(fn ($livewire): bool => filled($livewire->evrakError ?? null) || ! empty($livewire->evrakWarnings ?? []) || ! empty($livewire->evrakOk ?? []))
                    ->content(fn ($livewire): HtmlString => new HtmlString(self::warningsHtml($livewire->evrakError ?? null, $livewire->evrakWarnings ?? [], $livewire->evrakOk ?? []))),
            ],
        );
    }

    // Evrak doğrulama sonucunu form altında kutu olarak render eder.
    public static function warningsHtml(?string $error, array $warnings, array $ok = []): string
    {
        if (filled($error)) {
            return '<div style="border:1px solid #ef4444;background:#fef2f2;border-radius:8px;padding:12px 14px;">'
                .'<div style="font-weight:700;color:#b91c1c;font-size:13px;margin-bottom:4px;">⛔ Evrak doğrulanamadı — kaydedilmedi</div>'
                .'<div style="color:#991b1b;font-size:12.5px;line-height:1.5;">'.e($error).'</div></div>';
        }

        $html = '';

        if ($ok !== []) {
            $items = collect($ok)->map(fn (string $w): string => '<li>'.e($w).'</li>')->implode('');
            $html .= '<div style="border:1px solid #16a34a;background:#f0fdf4;border-radius:8px;padding:12px 14px;margin-bottom:'.($warnings !== [] ? '10px' : '0').';">'
                .'<div style="font-weight:700;color:#15803d;font-size:13px;margin-bottom:6px;">✓ Doğrulandı</div>'
                .'<ul style="margin:0;padding-left:18px;color:#166534;font-size:12.5px;line-height:1.6;">'.$items.'</ul></div>';
        }

        if ($warnings !== []) {
            $items = collect($warnings)->map(fn (string $w): string => '<li>'.e($w).'</li>')->implode('');
            $html .= '<div style="border:1px solid #f59e0b;background:#fffbeb;border-radius:8px;padding:12px 14px;">'
                .'<div style="font-weight:700;color:#b45309;font-size:13px;margin-bottom:6px;">⚠ Evrak kontrol uyarısı — elle doğrulayın</div>'
                .'<ul style="margin:0;padding-left:18px;color:#92400e;font-size:12.5px;line-height:1.6;">'.$items.'</ul></div>';
        }

        return $html;
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
                    ->url(fn (LegacyCustomer $r): string => EvrakResource::getUrl('yukle', ['record' => $r])),
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
            'yukle' => Pages\UploadEvrak::route('/{record}/yukle'),
        ];
    }
}