<?php

namespace App\Filament\Resources\Evrak;

use App\Filament\Resources\Evrak\Pages;
use App\Models\LegacyCustomer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EvrakResource extends Resource
{
    protected static ?string $model = LegacyCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'evrak';

    protected static ?string $navigationLabel = 'Evrak';

    protected static ?string $modelLabel = 'Evrak';

    protected static ?string $pluralModelLabel = 'Evrak';

    protected static string|\UnitEnum|null $navigationGroup = 'Veri';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Müşteri')->searchable()->sortable(),
            TextColumn::make('phone')->label('Telefon')->searchable(),
            TextColumn::make('kimlik')->label('Kimlik')->badge()
                ->state(fn (LegacyCustomer $record) => $record->hasDocument('identity_front') && $record->hasDocument('identity_back') ? 'Var' : 'Eksik')
                ->color(fn (string $state) => $state === 'Var' ? 'success' : 'danger'),
            TextColumn::make('sozlesme')->label('Sözleşme')->badge()
                ->state(fn (LegacyCustomer $record) => $record->hasDocument('contract') ? 'Var' : 'Eksik')
                ->color(fn (string $state) => $state === 'Var' ? 'success' : 'danger'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvrak::route('/'),
        ];
    }
}
