<?php

namespace App\Filament\Resources\Vade;

use App\Filament\Resources\Vade\Pages;
use App\Models\LegacyCustomer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VadeResource extends Resource
{
    protected static ?string $model = LegacyCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $slug = 'vade';

    protected static ?string $navigationLabel = 'Vade';

    protected static ?string $modelLabel = 'Vade';

    protected static ?string $pluralModelLabel = 'Vade';

    protected static string|\UnitEnum|null $navigationGroup = 'Veri';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Müşteri')->disabled(),
            Select::make('invoice_timing_mode')->label('Fatura Kesim Zamanı')
                ->options([
                    'delayed' => 'Ödeme sonrası',
                    'immediate' => 'Ödeme anında',
                    'advance' => 'Vade öncesi',
                ])->native(false)->live(),
            TextInput::make('invoice_timing_grace_hours')->label('Ödeme sonrası bekleme (saat)')->numeric()
                ->visible(fn ($get) => $get('invoice_timing_mode') === 'delayed'),
            TextInput::make('invoice_timing_advance_days')->label('Vade öncesi kesim (gün)')->numeric()
                ->visible(fn ($get) => $get('invoice_timing_mode') === 'advance'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Müşteri')->searchable()->sortable(),
            TextColumn::make('package_name')->label('Paket')->searchable(),
            TextColumn::make('invoice_timing_mode')->label('Vade')->badge()
                ->formatStateUsing(fn ($state) => match ($state) {
                    'delayed' => 'Ödeme sonrası',
                    'immediate' => 'Anında',
                    'advance' => 'Vade öncesi',
                    default => 'Varsayılan',
                })
                ->color(fn ($state) => match ($state) {
                    'delayed' => 'success',
                    'immediate' => 'danger',
                    'advance' => 'info',
                    default => 'gray',
                }),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVade::route('/'),
            'edit' => Pages\EditVade::route('/{record}/edit'),
        ];
    }
}
