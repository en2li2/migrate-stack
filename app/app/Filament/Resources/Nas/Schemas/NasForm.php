<?php

namespace App\Filament\Resources\Nas\Schemas;

use App\Models\LegacyNas;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class NasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('NAS Adı')->required()->maxLength(190),
                TextInput::make('shortname')->label('Kısa ad')->maxLength(190),
                TextInput::make('nas_ip_address')->label('NAS IP')->maxLength(190),
                TextInput::make('secret')->label('RADIUS Secret')->maxLength(190),
                TextInput::make('type')->label('Tip')->maxLength(120),
                TextInput::make('ports')->label('Portlar')->maxLength(60),
                Select::make('status')->label('Durum')->native(false)->options([
                    'active' => 'Aktif',
                    'inactive' => 'Pasif',
                ])->default('active'),
                Textarea::make('description')->label('Açıklama')->rows(2)->columnSpanFull(),

                Toggle::make('api_enabled')->label('API Etkin'),
                Grid::make(2)->schema([
                    TextInput::make('api_host')->label('API Host')->maxLength(190),
                    TextInput::make('api_port')->label('API Port')->numeric(),
                    TextInput::make('api_username')->label('API Kullanıcı')->maxLength(190),
                    TextInput::make('api_password')->label('API Parola')->maxLength(190),
                ])->columnSpanFull(),
                Toggle::make('api_tls')->label('API TLS'),

                Placeholder::make('legacy_info')
                    ->label('Legacy')
                    ->content(fn (?LegacyNas $record): string => $record
                        ? 'ID '.($record->legacy_id ?? '—').' · son senkron '.($record->legacy_synced_at?->format('d.m.Y H:i') ?? '—')
                        : '—')
                    ->columnSpanFull(),
            ]);
    }
}
