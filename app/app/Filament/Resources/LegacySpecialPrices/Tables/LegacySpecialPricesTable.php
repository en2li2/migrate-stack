<?php

namespace App\Filament\Resources\LegacySpecialPrices\Tables;

use App\Models\LegacyCustomer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegacySpecialPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legacy_customer_id')
                    ->label('Müşteri')
                    ->searchable()
                    ->formatStateUsing(fn ($state): string => LegacyCustomer::find($state)?->name ?: (string) $state),
                TextColumn::make('package_name')
                    ->label('Paket')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->label('Özel Fiyat')
                    ->money('TRY')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Başlangıç')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Bitiş')
                    ->date('d.m.Y')
                    ->placeholder('süresiz')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('legacy_source')
                    ->label('Kaynak')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Seçilileri sil'),
                ]),
            ]);
    }
}
