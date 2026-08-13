<?php

namespace App\Filament\Resources\LegacyCustomers\Schemas;

use App\Filament\Forms\Components\StructuredAddressFields;
use App\Models\LegacyCustomer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    Textarea::make('bilgi')->label('Bilgi')
                        ->placeholder('Aynı TC/VKN altındaki aboneliği ayırt etmek için not (ev / iş yeri / 2. hat …)')
                        ->rows(2)->maxLength(500)->columnSpanFull(),
                    Toggle::make('is_free')
                        ->label('Ücretsiz')
                        ->helperText('Açık ise bu müşteri ücretsiz (faturasız) internet kullanıcısı olarak işaretlenir.')
                        ->inline(false)
                        ->columnSpanFull(),
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
        if ($record === null || $record->getKey() === null) {
            return '<div style="color:#6b7280;font-size:13px;">Kayıt yok.</div>';
        }

        // Yerel migrate DB'den okunur (Legacy Sync ile doldurulur).
        $rows = DB::table('legacy_customer_packages')
            ->where('legacy_customer_id', $record->getKey())
            ->orderByDesc('ends_at')
            ->get(['status_code', 'package_name', 'price', 'starts_at', 'ends_at']);

        if ($rows->isEmpty()) {
            return '<div style="color:#9ca3af;font-size:13px;">Paket kaydı yok. <b>Legacy Sync</b> ile senkronlayın.</div>';
        }

        // status_code: 1=Aktif, 0=Bekleyen, 2=Geçmiş
        $sections = [1 => ['Aktif Paket', '#16a34a'], 0 => ['Bekleyen Paket', '#d97706'], 2 => ['Geçmiş Paket', '#6b7280']];
        $html = '<div style="display:grid;gap:20px;">';

        foreach ($sections as $status => [$title, $color]) {
            $items = $rows->where('status_code', $status)->values();
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
                        .'<td style="padding:5px 8px;font-weight:600;">'.e($r->package_name ?: '—').'</td>'
                        .'<td style="padding:5px 8px;">'.e(static::fmtDate($r->starts_at)).'</td>'
                        .'<td style="padding:5px 8px;">'.e(static::fmtDate($r->ends_at)).'</td>'
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

    /** Kullanım (oturum) geçmişi — legacy radacct'ten, pppoe_username ile eşleşir. */
    protected static function usageHtml(?LegacyCustomer $record): string
    {
        if ($record === null || $record->getKey() === null) {
            return '<div style="color:#6b7280;font-size:13px;">Kayıt yok.</div>';
        }

        // Yerel migrate DB'den okunur (Legacy Sync radacct'ten doldurur).
        $rows = DB::table('legacy_customer_usages')
            ->where('legacy_customer_id', $record->getKey())
            ->orderByDesc('started_at')
            ->limit(50)
            ->get(['started_at', 'stopped_at', 'session_time', 'download_bytes', 'upload_bytes', 'framed_ip']);
        $totals = DB::table('legacy_customer_usages')
            ->where('legacy_customer_id', $record->getKey())
            ->selectRaw('COUNT(*) c, COALESCE(SUM(download_bytes),0) dl, COALESCE(SUM(upload_bytes),0) ul')
            ->first();

        if ($rows->isEmpty()) {
            return '<div style="color:#9ca3af;font-size:13px;">Kullanım kaydı yok. <b>Legacy Sync</b> ile senkronlayın.</div>';
        }

        $html = '<div style="margin-bottom:12px;font-size:12.5px;color:#374151;">'
            .'<b>'.(int) $totals->c.'</b> oturum · Toplam indirme <b>'.static::bytes($totals->dl).'</b> · yükleme <b>'.static::bytes($totals->ul).'</b></div>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            .'<thead><tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb;">'
            .'<th style="padding:5px 8px;">Başlangıç</th><th style="padding:5px 8px;">Bitiş</th>'
            .'<th style="padding:5px 8px;">Süre</th><th style="padding:5px 8px;">İndirme</th>'
            .'<th style="padding:5px 8px;">Yükleme</th><th style="padding:5px 8px;">IP</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $stop = blank($r->stopped_at)
                ? '<span style="color:#16a34a;font-weight:700;">● aktif</span>'
                : e(static::fmtDateTime($r->stopped_at));
            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">'
                .'<td style="padding:5px 8px;">'.e(static::fmtDateTime($r->started_at)).'</td>'
                .'<td style="padding:5px 8px;">'.$stop.'</td>'
                .'<td style="padding:5px 8px;">'.e(static::duration((int) $r->session_time)).'</td>'
                .'<td style="padding:5px 8px;">'.e(static::bytes($r->download_bytes)).'</td>'
                .'<td style="padding:5px 8px;">'.e(static::bytes($r->upload_bytes)).'</td>'
                .'<td style="padding:5px 8px;font-family:ui-monospace,monospace;color:#6b7280;">'.e($r->framed_ip ?: '—').'</td></tr>';
        }
        $html .= '</tbody></table>';
        if ((int) $totals->c > 50) {
            $html .= '<div style="color:#9ca3af;font-size:11.5px;margin-top:4px;">Son 50 oturum gösteriliyor · toplam '.(int) $totals->c.'</div>';
        }

        return $html;
    }

    protected static function fmtDateTime(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected static function bytes(mixed $n): string
    {
        $n = (float) $n;
        if ($n <= 0) {
            return '0';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($n, 1024));
        $i = max(0, min($i, count($units) - 1));

        return number_format($n / (1024 ** $i), $i >= 2 ? 2 : 0, ',', '.').' '.$units[$i];
    }

    protected static function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $h.'s '.$m.'d';
        }

        return $m.'d';
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
