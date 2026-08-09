<?php

namespace App\Filament\Resources\LegacyPackages\Schemas;

use App\Models\LegacyPackage;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LegacyPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Ad')->required()->maxLength(190),
                TextInput::make('code')->label('Kod')->maxLength(60),

                TextInput::make('download_rate')->label('İndirme')->numeric()->suffix('bit/s')
                    ->helperText('Örn. 52953088 ≈ 50 Mbps'),
                TextInput::make('upload_rate')->label('Yükleme')->numeric()->suffix('bit/s'),

                TextInput::make('price')->label('Fiyat')->numeric()->prefix('₺'),
                TextInput::make('currency')->label('Para birimi')->default('TRY')->maxLength(8),

                Grid::make(3)->schema([
                    TextInput::make('duration_value')->label('Süre değeri')->numeric(),
                    Select::make('duration_type')->label('Süre tipi')->native(false)->options([
                        'day' => 'Gün',
                        'week' => 'Hafta',
                        'month' => 'Ay',
                        'year' => 'Yıl',
                    ]),
                    TextInput::make('duration_days')->label('Gün karşılığı')->numeric(),
                ])->columnSpanFull(),

                TextInput::make('radius_group_name')->label('RADIUS grubu')->maxLength(120),
                TextInput::make('framed_pool')->label('IP havuzu')->maxLength(120),
                TextInput::make('simultaneous_use')->label('Eşzamanlı oturum')->numeric(),
                Toggle::make('is_active')->label('Aktif')->default(true),

                Textarea::make('description')->label('Açıklama')->rows(2)->columnSpanFull(),

                Placeholder::make('legacy_info')
                    ->label('Legacy')
                    ->content(fn (?LegacyPackage $record): string => $record
                        ? 'ID '.($record->legacy_id ?? '—').' · son senkron '.($record->legacy_synced_at?->format('d.m.Y H:i') ?? '—')
                        : '—')
                    ->columnSpanFull(),
            ]);
    }
}
