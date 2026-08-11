<x-filament-panels::page>
    @php
        $p = $progress ?? [];
        $info = $customerInfo ?? [];
        $tones = [
            'otomatik-hazır' => ['bg' => 'rgba(22,163,74,.12)', 'br' => 'rgba(22,163,74,.35)', 'fg' => '#15803d', 'label' => 'Otomatik hazır'],
            'öneri-kontrol' => ['bg' => 'rgba(234,88,12,.12)', 'br' => 'rgba(234,88,12,.35)', 'fg' => '#b45309', 'label' => 'Öneri — kontrol et'],
            'elle-gerekli' => ['bg' => 'rgba(100,116,139,.14)', 'br' => 'rgba(100,116,139,.35)', 'fg' => '#475569', 'label' => 'Elle doldur'],
        ];
        $tone = $tones[$info['status'] ?? ''] ?? $tones['elle-gerekli'];
    @endphp

    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;border:1px solid rgba(148,163,184,.28);border-radius:12px;background:#f8fafc;padding:10px 14px;margin-bottom:14px;">
        <div style="font-size:13px;color:#475569;">
            <strong style="color:#0f172a;">{{ $p['done'] ?? 0 }}</strong> / {{ $p['total'] ?? 0 }} tamamlandı
            <span style="color:#94a3b8;">·</span> kalan <strong style="color:#0f172a;">{{ $p['remaining'] ?? 0 }}</strong>
            @if (($p['skipped'] ?? 0) > 0) <span style="color:#94a3b8;">·</span> atlanan {{ $p['skipped'] }} @endif
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;font-size:11.5px;font-weight:600;">
            <span style="border:1px solid rgba(22,163,74,.35);background:rgba(22,163,74,.12);color:#15803d;border-radius:999px;padding:3px 9px;">Hazır {{ $p['groups']['otomatik-hazır'] ?? 0 }}</span>
            <span style="border:1px solid rgba(234,88,12,.35);background:rgba(234,88,12,.12);color:#b45309;border-radius:999px;padding:3px 9px;">Öneri {{ $p['groups']['öneri-kontrol'] ?? 0 }}</span>
            <span style="border:1px solid rgba(100,116,139,.35);background:rgba(100,116,139,.14);color:#475569;border-radius:999px;padding:3px 9px;">Elle {{ $p['groups']['elle-gerekli'] ?? 0 }}</span>
        </div>
    </div>

    @if ($finished)
        <div style="border:1px solid rgba(22,163,74,.35);background:rgba(22,163,74,.08);border-radius:14px;padding:28px;text-align:center;">
            <div style="font-size:17px;font-weight:700;color:#15803d;margin-bottom:6px;">Sıra bitti 🎉</div>
            <div style="font-size:13.5px;color:#475569;">İşlenecek adres kalmadı.
                @if (($p['skipped'] ?? 0) > 0) Bu oturumda <strong>{{ $p['skipped'] }}</strong> müşteriyi atlamıştın. @endif
            </div>
            @if (($p['skipped'] ?? 0) > 0)
                <div style="margin-top:14px;"><x-filament::button wire:click="resetSkipped" color="warning">Atlananlara dön</x-filament::button></div>
            @endif
        </div>
    @else
        <div style="border:1px solid rgba(148,163,184,.3);border-radius:14px;background:#fff;padding:16px 18px;margin-bottom:12px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div style="min-width:0;">
                    <div style="font-size:18px;font-weight:700;color:#0f172a;line-height:1.25;">{{ $info['name'] ?? '' }}</div>
                    <div style="font-size:12.5px;color:#64748b;margin-top:3px;">{{ $info['pppoe'] ?? '' }}</div>
                </div>
                <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start;">
                    <div>
                        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">{{ $info['identity_label'] ?? 'TC Kimlik' }}</div>
                        <div style="font-size:14.5px;font-weight:700;color:#0f172a;margin-top:2px;">{{ $info['identity'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">Telefon</div>
                        <div style="font-size:14.5px;font-weight:700;color:#0f172a;margin-top:2px;">{{ $info['phone'] ?? '—' }}</div>
                    </div>
                    <span style="display:inline-block;border:1px solid {{ $tone['br'] }};background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;white-space:nowrap;">{{ $tone['label'] }}</span>
                </div>
            </div>
        </div>

        <div style="border:1px dashed rgba(148,163,184,.55);border-radius:12px;background:#f8fafc;padding:12px 14px;margin-bottom:14px;">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;">Eski paneldeki adres</div>
            <div style="font-size:14px;color:#0f172a;line-height:1.5;">{{ $info['legacy_address'] ?? '—' }}</div>
            @if (filled($info['suggestion'] ?? '') && ($info['suggestion'] ?? '—') !== '—')
                <div style="margin-top:9px;padding-top:9px;border-top:1px solid rgba(148,163,184,.25);font-size:12px;color:#64748b;">
                    Öneri: <strong style="color:#0e7490;">{{ $info['suggestion'] }}</strong>
                    @if (filled($info['match_type'] ?? '')) <span style="color:#94a3b8;">·</span> {{ $info['match_type'] }}@if(!is_null($info['levenshtein'] ?? null)) (lev {{ $info['levenshtein'] }})@endif @endif
                    @if (filled($info['candidate'] ?? '')) <span style="color:#94a3b8;">·</span> okunan: {{ $info['candidate'] }} @endif
                </div>
            @endif
        </div>

        <form wire:submit="approve">
            {{ $this->form }}
            <div style="display:flex;gap:10px;align-items:center;margin-top:18px;">
                <x-filament::button type="submit" size="lg" icon="heroicon-m-check">Onayla ve Sonraki</x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="skip" icon="heroicon-m-arrow-right">Atla</x-filament::button>
                <span style="font-size:12px;color:#94a3b8;">Onayladığın adres müşteri kartına işlenir; sync artık onu ezmez.</span>
            </div>
        </form>
    @endif
</x-filament-panels::page>
