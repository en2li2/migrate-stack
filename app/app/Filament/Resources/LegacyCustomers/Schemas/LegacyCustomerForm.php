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
use Illuminate\Support\Facades\DB;
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

                // ── PAKET ─────────────────────────────────────────────────
                Tab::make('Paket')->schema([
                    Placeholder::make('packages')
                        ->hiddenLabel()
                        ->content(fn (?LegacyCustomer $record): HtmlString => new HtmlString(static::packagesHtml($record)))
                        ->columnSpanFull(),
                ]),

                // ── KULLANIM ──────────────────────────────────────────────
                Tab::make('Kullanım')->schema([
                    Placeholder::make('usage')
                        ->hiddenLabel()
                        ->content(fn (?LegacyCustomer $record): HtmlString => new HtmlString(static::usageHtml($record)))
                        ->columnSpanFull(),
                ]),

            ]),
        ]);
    }

    /** Aktif / Bekleyen / Geçmiş paketler — legacy raduserqueue + groupinfo'dan (canlı okuma). */
    protected static function packagesHtml(?LegacyCustomer $record): string
    {
        if ($record === null || blank($record->legacy_id)) {
            return '<div style="color:#6b7280;font-size:13px;">Kayıt legacy ile eşleşmemiş.</div>';
        }

        try {
            $rows = DB::connection('legacy')->table('raduserqueue as q')
                ->leftJoin('groupinfo as g', 'g.id', '=', 'q.groupId')
                ->where('q.userId', $record->legacy_id)
                ->orderByDesc('q.dateTo')
                ->get(['q.status', 'q.dateFrom', 'q.dateTo', 'q.quantity', 'g.name', 'g.price']);
        } catch (\Throwable) {
            return '<div style="color:#b91c1c;font-size:13px;">Legacy bağlantısı okunamadı.</div>';
        }

        // status: 1=Aktif, 0=Bekleyen, 2=Geçmiş
        $sections = [1 => ['Aktif Paket', '#16a34a'], 0 => ['Bekleyen Paket', '#d97706'], 2 => ['Geçmiş Paket', '#6b7280']];
        $html = '<div style="display:grid;gap:20px;">';

        foreach ($sections as $status => [$title, $color]) {
            $items = $rows->where('status', $status)->values();
            $limited = $items->take(60);
            $html .= '<div>';
            $html .= '<div style="font-weight:750;font-size:13px;color:'.$color.';margin-bottom:6px;text-transform:uppercase;letter-spacing:.03em;">'
                .e($title).' <span style="color:#9ca3af;font-weight:600;">('.$items->count().')</span></div>';

            if ($items->isEmpty()) {
                $html .= '<div style="color:#9ca3af;font-size:12.5px;">—</div>';
            } else {
                $html .= '<table style="width:100%;border-collapse:collapse;font-size:12.5px;">'
                    .'<thead><tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb;">'
                    .'<th style="padding:5px 8px;">Paket</th><th style="padding:5px 8px;">Başlangıç</th>'
                    .'<th style="padding:5px 8px;">Bitiş</th><th style="padding:5px 8px;">Fiyat</th></tr></thead><tbody>';
                foreach ($limited as $r) {
                    $price = $r->price !== null && $r->price !== '' ? number_format((float) $r->price, 2, ',', '.').' ₺' : '—';
                    $html .= '<tr style="border-bottom:1px solid #f3f4f6;">'
                        .'<td style="padding:5px 8px;font-weight:600;">'.e($r->name ?: '—').'</td>'
                        .'<td style="padding:5px 8px;">'.e(static::fmtDate($r->dateFrom)).'</td>'
                        .'<td style="padding:5px 8px;">'.e(static::fmtDate($r->dateTo)).'</td>'
                        .'<td style="padding:5px 8px;">'.e($price).'</td></tr>';
                }
                $html .= '</tbody></table>';
                if ($items->count() > 60) {
                    $html .= '<div style="color:#9ca3af;font-size:11.5px;margin-top:4px;">… ve '.($items->count() - 60).' kayıt daha</div>';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    protected static function usageHtml(?LegacyCustomer $record): string
    {
        // Legacy DB'de kullanım/accounting (radacct) verisi yok. Kullanım geçmişi
        // canlı RADIUS accounting kaynağı bağlanınca burada listelenecek.
        return '<div style="color:#6b7280;font-size:13px;line-height:1.6;">'
            .'Kullanım geçmişi için legacy veritabanında accounting (radacct) verisi bulunmuyor.<br>'
            .'Canlı RADIUS kullanım kaynağı bağlandığında bu sekmede oturum/trafik geçmişi listelenecek.'
            .'</div>';
    }

    protected static function fmtDate(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
