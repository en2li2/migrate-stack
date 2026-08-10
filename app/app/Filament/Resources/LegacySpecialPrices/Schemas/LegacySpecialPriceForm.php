<?php

namespace App\Filament\Resources\LegacySpecialPrices\Schemas;

use App\Models\LegacyCustomer;
use App\Models\LegacyPackage;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LegacySpecialPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // Müşteri ara + seç (YEREL id saklanır; FK legacy_customers.id).
                Select::make('legacy_customer_id')
                    ->label('Müşteri')
                    ->searchable()
                    ->required()
                    ->columnSpanFull()
                    ->getSearchResultsUsing(fn (string $search): array => LegacyCustomer::query()
                        ->where(function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('pppoe_username', 'like', "%{$search}%")
                                ->orWhere('company_title', 'like', "%{$search}%")
                                ->orWhere('national_id', 'like', "%{$search}%");
                        })
                        ->limit(40)
                        ->get()
                        ->mapWithKeys(fn (LegacyCustomer $c): array => [$c->id => self::customerLabel($c)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($c = LegacyCustomer::find($value)) ? self::customerLabel($c) : null)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $c = LegacyCustomer::find($state);
                        if ($c && filled($c->package_name)) {
                            $pkg = LegacyPackage::where('name', $c->package_name)->first();
                            if ($pkg) {
                                $set('legacy_package_id', $pkg->id);
                                $set('package_name', $pkg->name);
                            }
                        }
                    }),

                // Paket (müşteri seçilince otomatik gelir, değiştirilebilir).
                Select::make('legacy_package_id')
                    ->label('Paket')
                    ->searchable()
                    ->options(fn (): array => LegacyPackage::orderBy('name')->pluck('name', 'id')->all())
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $p = LegacyPackage::find($state);
                        $set('package_name', $p?->name);
                    }),
                Placeholder::make('list_price')
                    ->label('Liste fiyatı')
                    ->content(function ($get): string {
                        $p = LegacyPackage::find($get('legacy_package_id'));

                        return $p && $p->price !== null
                            ? number_format((float) $p->price, 2, ',', '.').' '.($p->currency ?: 'TRY')
                            : '—';
                    }),

                Hidden::make('package_name'),

                TextInput::make('price')->label('Özel Fiyat')->numeric()->prefix('₺')->required(),
                TextInput::make('currency')->label('Para birimi')->default('TRY')->maxLength(8),

                Grid::make(2)->schema([
                    DatePicker::make('starts_at')->label('Başlangıç')->native(false)->displayFormat('d.m.Y')
                        ->hintAction(
                            Action::make('startNow')->label('Şimdi')
                                ->action(fn ($set) => $set('starts_at', now()->timezone('Europe/Istanbul')->toDateString())),
                        ),
                    DatePicker::make('ends_at')->label('Bitiş')->native(false)->displayFormat('d.m.Y')->required()
                        ->hintAction(
                            Action::make('endYear')->label('Yıl sonu')
                                ->action(fn ($set) => $set('ends_at', now()->timezone('Europe/Istanbul')->endOfYear()->toDateString())),
                        ),
                ])->columnSpanFull(),

                Toggle::make('is_active')->label('Aktif')->default(true),

                Textarea::make('notes')->label('Not')->rows(2)->columnSpanFull(),
            ]);
    }

    private static function customerLabel(LegacyCustomer $c): string
    {
        $name = $c->name ?: ($c->company_title ?: '—');
        $extra = collect([$c->pppoe_username, $c->national_id])->filter()->implode(' · ');

        return $extra !== '' ? "{$name} ({$extra})" : $name;
    }
}
