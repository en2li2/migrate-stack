<?php

namespace App\Filament\Resources\Nas;

use App\Filament\Resources\Nas\Pages\EditNas;
use App\Filament\Resources\Nas\Pages\ListNas;
use App\Filament\Resources\Nas\Schemas\NasForm;
use App\Filament\Resources\Nas\Tables\NasTable;
use App\Models\LegacyNas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NasResource extends Resource
{
    protected static ?string $model = LegacyNas::class;

    // Ara/geçiş paneli: NAS oluşturma yok, yalnız Legacy Sync + düzenleme.
    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'NAS';
    protected static ?string $modelLabel = 'NAS';
    protected static ?string $pluralModelLabel = 'NAS';
    protected static string|\UnitEnum|null $navigationGroup = 'Veri';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return NasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNas::route('/'),
            'edit' => EditNas::route('/{record}/edit'),
        ];
    }
}
