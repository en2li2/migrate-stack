<?php

namespace App\Filament\Resources\LegacyPackages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegacyPackagesTable
{
    public static function configure(Table $table): Table
    {
        $mbps = static fn ($state): string => $state ? round(((int) $state) / 1048576, 1).' Mbps' : '—';

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('legacy_id')
                    ->label('Legacy ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kod')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('download_rate')
                    ->label('İndirme')
                    ->formatStateUsing($mbps)
                    ->sortable(),
                TextColumn::make('upload_rate')
                    ->label('Yükleme')
                    ->formatStateUsing($mbps)
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Fiyat')
                    ->money('TRY')
                    ->sortable(),
                TextColumn::make('duration_value')
                    ->label('Süre')
                    ->formatStateUsing(fn ($state, $record): string => $state ? $state.' '.self::durLabel($record->duration_type) : '—')
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('Gün')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('radius_group_name')
                    ->label('RADIUS grubu')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('framed_pool')
                    ->label('IP havuzu')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('simultaneous_use')
                    ->label('Eşzamanlı')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('legacy_synced_at')
                    ->label('Son senkron')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
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

    private static function durLabel(?string $type): string
    {
        return match ($type) {
            'month' => 'ay',
            'week' => 'hafta',
            'year' => 'yıl',
            'day' => 'gün',
            default => (string) $type,
        };
    }
}
