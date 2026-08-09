<?php

namespace App\Filament\Resources\LegacyCustomers\Schemas;

use App\Filament\Forms\Components\StructuredAddressFields;
use App\Models\LegacyCustomer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class LegacyCustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('customer')->columnSpanFull()->tabs([

                // ── BİLGİLER ──────────────────────────────────────────────
                Tab::make('Bilgiler')->schema([
                    TextInput::make('pppoe_username')->label('PPPoE Kullanıcı')->disabled(),
                    Select::make('customer_type')
                        ->label('Tip')
                        ->options(['individual' => 'Bireysel', 'company' => 'Kurumsal'])
                        ->default('individual')
                        ->required()
                        ->live(),
                    TextInput::make('first_name')->label('Ad')->maxLength(160)
                        ->visible(fn ($get): bool => $get('customer_type') !== 'company'),
                    TextInput::make('last_name')->label('Soyad')->maxLength(80)
                        ->visible(fn ($get): bool => $get('customer_type') !== 'company'),
                    TextInput::make('company_title')->label('Unvan')->maxLength(190)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('national_id')->label('TC Kimlik')->maxLength(20)
                        ->visible(fn ($get): bool => $get('customer_type') !== 'company'),
                    TextInput::make('authorized_first_name')->label('Yetkili Adı')->maxLength(120)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('authorized_last_name')->label('Yetkili Soyadı')->maxLength(120)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('authorized_national_id')->label('Yetkili TC Kimlik')->maxLength(20)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('tax_number')->label('Vergi No')->maxLength(30)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('tax_office')->label('Vergi Dairesi')->maxLength(120)
                        ->visible(fn ($get): bool => $get('customer_type') === 'company'),
                    TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
                    TextInput::make('phone2')->label('Telefon 2')->tel()->maxLength(30),
                    TextInput::make('email')->label('E-posta')->email()->maxLength(160),
                ])->columns(2),

                // ── ADRES ─────────────────────────────────────────────────
                Tab::make('Adres')->schema([
                    Placeholder::make('legacy_address')
                        ->label('Eski Adres (legacy)')
                        ->content(fn (?LegacyCustomer $record): string => (string) ($record?->address ?: '—'))
                        ->visible(fn (?LegacyCustomer $record): bool => $record === null || blank($record->new_address_text))
                        ->columnSpanFull(),
                    ...StructuredAddressFields::make(),
                ]),

                // ── FATURA ────────────────────────────────────────────────
                Tab::make('Fatura')->schema([
                    Select::make('invoice_timing_mode')
                        ->label('Fatura Kesim Zamanı')
                        ->native(false)
                        ->live()
                        ->placeholder('Genel varsayılan (ödeme sonrası 24 saat)')
                        ->options([
                            'delayed' => 'Ödeme sonrası (gecikmeli)',
                            'immediate' => 'Anında (ödeme anında)',
                            'advance' => 'Ödeme öncesi (vade öncesi)',
                        ])
                        ->helperText('Boş = genel varsayılan. Ödeme sonrası = yanlış ödeme/iade güvenliği; Ödeme öncesi = kurumsal/sözleşmeli.'),
                    Grid::make(2)->schema([
                        TextInput::make('invoice_timing_grace_hours')
                            ->label('Kaç saat sonra')->numeric()->minValue(0)->maxValue(720)->suffix('saat')->placeholder('24')
                            ->visible(fn ($get): bool => $get('invoice_timing_mode') === 'delayed'),
                        TextInput::make('invoice_timing_advance_days')
                            ->label('Kaç gün önce')->numeric()->minValue(0)->maxValue(90)->suffix('gün')->placeholder('7')
                            ->visible(fn ($get): bool => $get('invoice_timing_mode') === 'advance'),
                    ]),
                    Placeholder::make('package_info')
                        ->label('Paket (legacy)')
                        ->content(fn (?LegacyCustomer $record): HtmlString => new HtmlString(
                            '<div style="color:#374151;font-size:13px;">'
                            .e((string) ($record?->package_name ?: '—'))
                            .($record?->subscription_ends_at ? ' · bitiş '.e($record->subscription_ends_at->format('d.m.Y')) : '')
                            .'</div>'
                        ))
                        ->columnSpanFull(),
                ])->columns(2),

            ]),
        ]);
    }
}
