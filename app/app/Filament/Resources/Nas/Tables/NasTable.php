<?php

namespace App\Filament\Resources\Nas\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('legacy_id')
                    ->label('Legacy ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('NAS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shortname')
                    ->label('Kısa ad')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('nas_ip_address')
                    ->label('NAS IP')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Tip')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'Aktif' : ($state ?: '—'))
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
                IconColumn::make('api_enabled')
                    ->label('API')
                    ->boolean(),
                TextColumn::make('api_host')
                    ->label('API Host')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->label('Açıklama')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
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
            ]);
    }
}
