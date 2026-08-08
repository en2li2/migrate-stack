<?php

namespace App\Filament\Resources\LegacyPackages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LegacyPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legacy_source')
                    ->required()
                    ->default('proradiusmanager'),
                TextInput::make('legacy_id'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('code'),
                TextInput::make('download_rate')
                    ->numeric(),
                TextInput::make('upload_rate')
                    ->numeric(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('TRY'),
                TextInput::make('duration_days')
                    ->numeric(),
                TextInput::make('duration_type'),
                TextInput::make('duration_value')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('radius_group_name'),
                TextInput::make('framed_pool'),
                TextInput::make('simultaneous_use')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('locked_fields'),
                TextInput::make('legacy_payload'),
                DateTimePicker::make('legacy_synced_at'),
            ]);
    }
}
