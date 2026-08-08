<?php

namespace App\Filament\Resources\LegacySpecialPrices;

use App\Filament\Resources\LegacySpecialPrices\Pages\CreateLegacySpecialPrice;
use App\Filament\Resources\LegacySpecialPrices\Pages\EditLegacySpecialPrice;
use App\Filament\Resources\LegacySpecialPrices\Pages\ListLegacySpecialPrices;
use App\Filament\Resources\LegacySpecialPrices\Schemas\LegacySpecialPriceForm;
use App\Filament\Resources\LegacySpecialPrices\Tables\LegacySpecialPricesTable;
use App\Models\LegacySpecialPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LegacySpecialPriceResource extends Resource
{
    protected static ?string $model = LegacySpecialPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Özel Fiyatlar';
    protected static ?string $modelLabel = 'Özel Fiyat';
    protected static ?string $pluralModelLabel = 'Özel Fiyatlar';
    protected static string|\UnitEnum|null $navigationGroup = 'Veri';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return LegacySpecialPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacySpecialPricesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegacySpecialPrices::route('/'),
            'create' => CreateLegacySpecialPrice::route('/create'),
            'edit' => EditLegacySpecialPrice::route('/{record}/edit'),
        ];
    }
}
