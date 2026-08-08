<?php

namespace App\Filament\Resources\LegacyPackages;

use App\Filament\Resources\LegacyPackages\Pages\CreateLegacyPackage;
use App\Filament\Resources\LegacyPackages\Pages\EditLegacyPackage;
use App\Filament\Resources\LegacyPackages\Pages\ListLegacyPackages;
use App\Filament\Resources\LegacyPackages\Schemas\LegacyPackageForm;
use App\Filament\Resources\LegacyPackages\Tables\LegacyPackagesTable;
use App\Models\LegacyPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LegacyPackageResource extends Resource
{
    protected static ?string $model = LegacyPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Paketler';
    protected static ?string $modelLabel = 'Paket';
    protected static ?string $pluralModelLabel = 'Paketler';
    protected static string|\UnitEnum|null $navigationGroup = 'Veri';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return LegacyPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacyPackagesTable::configure($table);
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
            'index' => ListLegacyPackages::route('/'),
            'create' => CreateLegacyPackage::route('/create'),
            'edit' => EditLegacyPackage::route('/{record}/edit'),
        ];
    }
}
