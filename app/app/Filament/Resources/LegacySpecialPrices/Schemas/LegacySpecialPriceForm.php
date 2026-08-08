<?php

namespace App\Filament\Resources\LegacySpecialPrices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LegacySpecialPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legacy_customer_id')
                    ->numeric(),
                TextInput::make('legacy_package_id')
                    ->numeric(),
                TextInput::make('package_name'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('TRY'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('locked_fields'),
                TextInput::make('legacy_source')
                    ->required()
                    ->default('proradiusmanager'),
                TextInput::make('legacy_id'),
                DateTimePicker::make('legacy_synced_at'),
            ]);
    }
}
