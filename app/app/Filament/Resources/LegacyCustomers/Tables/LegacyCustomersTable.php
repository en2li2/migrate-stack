<?php

namespace App\Filament\Resources\LegacyCustomers\Tables;

use App\Models\LegacyCustomer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LegacyCustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Ad / Unvan')->weight('semibold')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('pppoe_username')->label('PPPoE')->searchable()->toggleable(),
                TextColumn::make('national_id')->label('TC / VKN')
                    ->state(fn (LegacyCustomer $r): string => $r->national_id ?: ($r->tax_number ?: '—')),
                TextColumn::make('phone')->label('Telefon')->placeholder('—'),
                TextColumn::make('customer_type')->label('Tip')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'company' ? 'Kurumsal' : 'Bireysel')
                    ->color(fn (string $state): string => $state === 'company' ? 'warning' : 'info'),
                TextColumn::make('package_name')->label('Paket')->placeholder('—')->toggleable(),
                TextColumn::make('subscription_ends_at')->label('Bitiş')->dateTime('d.m.Y')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('new_address_text')->label('Adres')->badge()->placeholder('—')
                    ->state(fn (LegacyCustomer $r): ?string => filled($r->new_address_text) ? 'Düzenlendi' : null)
                    ->color('success')
                    ->icon(fn (LegacyCustomer $r): ?string => filled($r->new_address_text) ? 'heroicon-m-map-pin' : null)
                    ->tooltip(fn (LegacyCustomer $r): ?string => $r->new_address_text)
                    ->toggleable(),
                TextColumn::make('invoice_timing_mode')->label('Fatura Kesimi')->badge()->placeholder('—')
                    ->state(fn (LegacyCustomer $r): ?string => match ($r->invoice_timing_mode) {
                        'immediate' => 'Anında',
                        'advance' => 'Ödeme öncesi '.((int) ($r->invoice_timing_advance_days ?: 7)).'g',
                        'delayed' => 'Ödeme sonrası '.((int) ($r->invoice_timing_grace_hours ?: 24)).'s',
                        default => null,
                    })
                    ->color(fn (LegacyCustomer $r): string => match ($r->invoice_timing_mode) {
                        'immediate' => 'danger', 'advance' => 'info', 'delayed' => 'success', default => 'gray',
                    })
                    ->icon(fn (LegacyCustomer $r): ?string => filled($r->invoice_timing_mode) ? 'heroicon-m-clock' : null)
                    ->toggleable(),
                TextColumn::make('legacy_synced_at')->label('Son Sync')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_type')->label('Tip')
                    ->options(['individual' => 'Bireysel', 'company' => 'Kurumsal']),
                TernaryFilter::make('adres_durumu')->label('Adres')
                    ->placeholder('Hepsi')->trueLabel('Düzenlendi')->falseLabel('Düzenlenmedi')
                    ->queries(
                        true: fn (Builder $q): Builder => $q->whereNotNull('new_address_text')->where('new_address_text', '!=', ''),
                        false: fn (Builder $q): Builder => $q->where(fn (Builder $w) => $w->whereNull('new_address_text')->orWhere('new_address_text', '')),
                    ),
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
