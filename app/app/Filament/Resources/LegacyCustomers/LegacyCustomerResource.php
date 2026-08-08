<?php

namespace App\Filament\Resources\LegacyCustomers;

use App\Filament\Resources\LegacyCustomers\Pages\CreateLegacyCustomer;
use App\Filament\Resources\LegacyCustomers\Pages\EditLegacyCustomer;
use App\Filament\Resources\LegacyCustomers\Pages\ListLegacyCustomers;
use App\Filament\Resources\LegacyCustomers\Schemas\LegacyCustomerForm;
use App\Filament\Resources\LegacyCustomers\Tables\LegacyCustomersTable;
use App\Models\LegacyCustomer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LegacyCustomerResource extends Resource
{
    protected static ?string $model = LegacyCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Müşteriler';

    protected static ?string $modelLabel = 'Müşteri';

    protected static ?string $pluralModelLabel = 'Müşteriler';

    protected static string|\UnitEnum|null $navigationGroup = 'Veri';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return LegacyCustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacyCustomersTable::configure($table);
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
            'index' => ListLegacyCustomers::route('/'),
            'create' => CreateLegacyCustomer::route('/create'),
            'edit' => EditLegacyCustomer::route('/{record}/edit'),
        ];
    }
}
