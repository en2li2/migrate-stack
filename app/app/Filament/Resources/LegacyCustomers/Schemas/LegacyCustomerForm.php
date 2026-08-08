<?php

namespace App\Filament\Resources\LegacyCustomers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LegacyCustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legacy_source')
                    ->required()
                    ->default('proradiusmanager'),
                TextInput::make('legacy_id'),
                TextInput::make('pppoe_username'),
                TextInput::make('subscriber_number'),
                TextInput::make('name'),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('company_title'),
                TextInput::make('customer_type')
                    ->required()
                    ->default('individual'),
                TextInput::make('national_id'),
                TextInput::make('tax_number'),
                TextInput::make('tax_office'),
                TextInput::make('authorized_first_name'),
                TextInput::make('authorized_last_name'),
                TextInput::make('authorized_national_id'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('phone2')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('address_city_id')
                    ->numeric(),
                TextInput::make('address_district_id')
                    ->numeric(),
                TextInput::make('address_neighborhood_id')
                    ->numeric(),
                TextInput::make('address_street_id')
                    ->numeric(),
                TextInput::make('address_building_name'),
                TextInput::make('address_building_no'),
                TextInput::make('address_apartment_no'),
                Textarea::make('structured_address_text')
                    ->columnSpanFull(),
                Textarea::make('new_address_text')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('service_package_id')
                    ->numeric(),
                TextInput::make('package_name'),
                TextInput::make('download_rate')
                    ->numeric(),
                TextInput::make('upload_rate')
                    ->numeric(),
                DateTimePicker::make('subscription_ends_at'),
                TextInput::make('static_ip'),
                TextInput::make('invoice_timing_mode'),
                TextInput::make('invoice_timing_grace_hours')
                    ->numeric(),
                TextInput::make('invoice_timing_advance_days')
                    ->numeric(),
                TextInput::make('documents'),
                TextInput::make('locked_fields'),
                TextInput::make('legacy_payload'),
                TextInput::make('sync_issues'),
                DateTimePicker::make('legacy_synced_at'),
            ]);
    }
}
