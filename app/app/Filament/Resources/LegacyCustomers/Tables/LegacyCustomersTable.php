<?php

namespace App\Filament\Resources\LegacyCustomers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegacyCustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legacy_source')
                    ->searchable(),
                TextColumn::make('legacy_id')
                    ->searchable(),
                TextColumn::make('pppoe_username')
                    ->searchable(),
                TextColumn::make('subscriber_number')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('company_title')
                    ->searchable(),
                TextColumn::make('customer_type')
                    ->searchable(),
                TextColumn::make('national_id')
                    ->searchable(),
                TextColumn::make('tax_number')
                    ->searchable(),
                TextColumn::make('tax_office')
                    ->searchable(),
                TextColumn::make('authorized_first_name')
                    ->searchable(),
                TextColumn::make('authorized_last_name')
                    ->searchable(),
                TextColumn::make('authorized_national_id')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('phone2')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('address_city_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('address_district_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('address_neighborhood_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('address_street_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('address_building_name')
                    ->searchable(),
                TextColumn::make('address_building_no')
                    ->searchable(),
                TextColumn::make('address_apartment_no')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('service_package_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('package_name')
                    ->searchable(),
                TextColumn::make('download_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('upload_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('subscription_ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('static_ip')
                    ->searchable(),
                TextColumn::make('invoice_timing_mode')
                    ->searchable(),
                TextColumn::make('invoice_timing_grace_hours')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('invoice_timing_advance_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('legacy_synced_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
