<?php

namespace App\Services;

use App\Models\LegacyCustomer;
use App\Models\LegacyCustomerPackage;
use App\Models\LegacyNas;
use App\Models\LegacyPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Migrate → ISP Core (stage/prod CRM) müşteri aktarımı.
 *
 * legacy_customers kayıtlarını parça parça imzalı HTTP ile CRM'in
 * /api/internal/migrate/customers ucuna gönderir; pppoe ile upsert olur.
 * Dönen crm_id + abone no migrate kaydına GERİ YAZILIR (crm_customer_id,
 * crm_subscriber_number) — böylece abone no sabit kalır, tekrar gönderim
 * kopya üretmez.
 */
class IspCoreImportService
{
    /**
     * Katalog + müşteri (paket bağıyla) — buton bunu çağırır. Katalog ÖNCE
     * gitmeli ki müşteri paketi ada göre çözülebilsin.
     *
     * @return array{packages: array<string,int>, customers: array<string,int>}
     */
    public function pushAll(bool $dryRun = false): array
    {
        return [
            'nas' => $this->pushNasDevices($dryRun),
            'packages' => $this->pushPackages($dryRun),
            'customers' => $this->pushCustomers($dryRun),
            'customer_packages' => $this->pushCustomerPackages($dryRun),
            'usage' => $this->pushUsage($dryRun),
            'special_prices' => $this->pushSpecialPrices($dryRun),
        ];
    }

    /**
     * Özel fiyatları (legacy_special_prices) CRM customer_special_package_prices'a gönderir.
     *
     * @return array<string, int>
     */
    public function pushSpecialPrices(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();
        $summary = ['total' => 0, 'upserted' => 0, 'skipped' => 0];

        $rows = DB::table('legacy_special_prices as sp')
            ->join('legacy_customers as lc', 'lc.id', '=', 'sp.legacy_customer_id')
            ->whereNotNull('lc.pppoe_username')
            ->where('lc.pppoe_username', '<>', '')
            ->select('lc.pppoe_username', 'sp.package_name', 'sp.price', 'sp.currency', 'sp.starts_at', 'sp.ends_at', 'sp.is_active', 'sp.notes')
            ->get();

        if ($rows->isEmpty()) {
            return $summary;
        }

        foreach ($rows->chunk(500) as $chunk) {
            $payload = $chunk->map(fn ($r): array => [
                'pppoe_username' => $r->pppoe_username,
                'package_name' => $r->package_name,
                'price' => $r->price,
                'currency' => $r->currency,
                'starts_at' => $r->starts_at,
                'ends_at' => $r->ends_at,
                'is_active' => $r->is_active,
                'notes' => $r->notes,
            ])->values()->all();

            $response = Http::withToken($token)->acceptJson()->asJson()->timeout(120)
                ->post($base.'/api/internal/migrate/special-prices', ['dry_run' => $dryRun, 'rows' => $payload]);

            if ($response->successful()) {
                $s = (array) $response->json('summary', []);
                foreach (['total', 'upserted', 'skipped'] as $k) {
                    $summary[$k] += (int) ($s[$k] ?? 0);
                }
            }
        }

        return $summary;
    }

    /**
     * Kullanım geçmişini (legacy_customer_usages) aya göre toplayıp CRM
     * customer_usage_summaries'a gönderir.
     *
     * @return array<string, int>
     */
    public function pushUsage(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();
        $summary = ['total' => 0, 'upserted' => 0, 'skipped' => 0];

        $rows = DB::table('legacy_customer_usages as lcu')
            ->join('legacy_customers as lc', 'lc.id', '=', 'lcu.legacy_customer_id')
            ->whereNotNull('lc.pppoe_username')
            ->where('lc.pppoe_username', '<>', '')
            ->groupBy('lc.pppoe_username', 'period')
            ->selectRaw("lc.pppoe_username, DATE_FORMAT(lcu.started_at, '%Y-%m') as period, ".
                'SUM(lcu.upload_bytes) as input_bytes, SUM(lcu.download_bytes) as output_bytes, '.
                'COUNT(*) as sessions, MIN(lcu.started_at) as first_at, MAX(lcu.started_at) as last_at')
            ->get();

        if ($rows->isEmpty()) {
            return $summary;
        }

        foreach ($rows->chunk(500) as $chunk) {
            $payload = $chunk->map(fn ($r): array => [
                'pppoe_username' => $r->pppoe_username,
                'period' => $r->period,
                'input_bytes' => (int) $r->input_bytes,
                'output_bytes' => (int) $r->output_bytes,
                'sessions' => (int) $r->sessions,
                'first_at' => $r->first_at,
                'last_at' => $r->last_at,
            ])->values()->all();

            $response = Http::withToken($token)->acceptJson()->asJson()->timeout(120)
                ->post($base.'/api/internal/migrate/usage', ['dry_run' => $dryRun, 'rows' => $payload]);

            if ($response->successful()) {
                $s = (array) $response->json('summary', []);
                foreach (['total', 'upserted', 'skipped'] as $k) {
                    $summary[$k] += (int) ($s[$k] ?? 0);
                }
            }
        }

        return $summary;
    }

    /**
     * Migrate NAS'larını (legacy_nas) CRM nas_devices'a gönderir.
     *
     * @return array<string, int>
     */
    public function pushNasDevices(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();
        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0, 'would_create' => 0, 'would_update' => 0];

        $devices = LegacyNas::query()->orderBy('id')->get()
            ->map(fn (LegacyNas $n): array => $this->toNasPayload($n))
            ->values()
            ->all();

        if ($devices === []) {
            return $summary;
        }

        $response = Http::withToken($token)->acceptJson()->asJson()->timeout(60)
            ->post($base.'/api/internal/migrate/nas', ['dry_run' => $dryRun, 'devices' => $devices]);

        if (! $response->successful()) {
            $summary['error'] = count($devices);

            return $summary;
        }

        foreach ((array) $response->json('results', []) as $r) {
            $action = (string) ($r['action'] ?? 'error');
            $summary['total']++;
            $summary[$action] = ($summary[$action] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * Müşteri geçmiş (status 2) + bekleyen (status 0) paketlerini gönderir.
     * Müşteri başına gruplanır (customer_id CRM'de pppoe ile çözülür).
     *
     * @return array<string, int>
     */
    public function pushCustomerPackages(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();
        $summary = ['customers' => 0, 'skipped' => 0, 'history' => 0, 'pending' => 0];

        LegacyCustomer::query()
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($base, $token, $dryRun, &$summary): void {
                // Bu partinin paketlerini yükle (16k satırı belleğe almadan; aktif=1 hariç).
                $byCustomer = LegacyCustomerPackage::query()
                    ->whereIn('legacy_customer_id', $chunk->pluck('id'))
                    ->whereIn('status_code', [0, 2])
                    ->get()
                    ->groupBy('legacy_customer_id');

                $payload = [];

                foreach ($chunk as $c) {
                    $rows = $byCustomer[$c->id] ?? collect();
                    if ($rows->isEmpty()) {
                        continue;
                    }

                    $history = [];
                    $pending = [];
                    foreach ($rows as $r) {
                        $item = [
                            'legacy_id' => (string) $r->legacy_queue_id,
                            'package_name' => $r->package_name,
                        ];
                        if ((int) $r->status_code === 2) {
                            $item['started_at'] = $r->starts_at;
                            $item['ended_at'] = $r->ends_at;
                            $history[] = $item;
                        } elseif ((int) $r->status_code === 0) {
                            $item['starts_at'] = $r->starts_at;
                            $item['ends_at'] = $r->ends_at;
                            $pending[] = $item;
                        }
                    }

                    if ($history !== [] || $pending !== []) {
                        $payload[] = [
                            'pppoe_username' => $c->pppoe_username,
                            'history' => $history,
                            'pending' => $pending,
                        ];
                    }
                }

                if ($payload === []) {
                    return;
                }

                $response = Http::withToken($token)->acceptJson()->asJson()->timeout(180)
                    ->post($base.'/api/internal/migrate/customer-packages', [
                        'dry_run' => $dryRun,
                        'customers' => $payload,
                    ]);

                if ($response->successful()) {
                    $s = (array) $response->json('summary', []);
                    foreach (['customers', 'skipped', 'history', 'pending'] as $k) {
                        $summary[$k] += (int) ($s[$k] ?? 0);
                    }
                }
            });

        return $summary;
    }

    /**
     * Katalog paketlerini (legacy_packages) CRM service_packages'a gönderir.
     *
     * @return array<string, int>
     */
    public function pushPackages(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();

        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0, 'would_create' => 0, 'would_update' => 0];

        $packages = LegacyPackage::query()
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('id')
            ->get()
            ->map(fn (LegacyPackage $p): array => $this->toPackagePayload($p))
            ->values()
            ->all();

        if ($packages === []) {
            return $summary;
        }

        $response = Http::withToken($token)->acceptJson()->asJson()->timeout(120)
            ->post($base.'/api/internal/migrate/packages', ['dry_run' => $dryRun, 'packages' => $packages]);

        if (! $response->successful()) {
            $summary['error'] = count($packages);

            return $summary;
        }

        foreach ((array) $response->json('results', []) as $r) {
            $action = (string) ($r['action'] ?? 'error');
            $summary['total']++;
            $summary[$action] = ($summary[$action] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function pushCustomers(bool $dryRun = false): array
    {
        [$base, $token] = $this->target();
        $batchSize = max(1, (int) config('isp_core_import.batch_size', 100));

        $summary = [
            'total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
            'error' => 0, 'would_create' => 0, 'would_update' => 0,
        ];

        LegacyCustomer::query()
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '<>', '')
            ->orderBy('id')
            ->chunkById($batchSize, function ($chunk) use ($base, $token, $dryRun, &$summary): void {
                $payload = $chunk->map(fn (LegacyCustomer $c): array => $this->toPayload($c))->values()->all();

                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(120)
                    ->post($base.'/api/internal/migrate/customers', [
                        'dry_run' => $dryRun,
                        'customers' => $payload,
                    ]);

                if (! $response->successful()) {
                    $summary['error'] += $chunk->count();
                    if (! $dryRun) {
                        foreach ($chunk as $c) {
                            $c->forceFill([
                                'crm_sync_status' => 'error',
                                'crm_sync_message' => 'HTTP '.$response->status(),
                                'crm_synced_at' => now(),
                            ])->saveQuietly();
                        }
                    }

                    return;
                }

                $byPppoe = [];
                foreach ((array) $response->json('results', []) as $r) {
                    if (! empty($r['pppoe'])) {
                        $byPppoe[(string) $r['pppoe']] = $r;
                    }
                }

                foreach ($chunk as $c) {
                    $r = $byPppoe[(string) $c->pppoe_username] ?? null;
                    if ($r === null) {
                        continue;
                    }

                    $action = (string) ($r['action'] ?? 'error');
                    $summary['total']++;
                    $summary[$action] = ($summary[$action] ?? 0) + 1;

                    if ($dryRun) {
                        continue;
                    }

                    if (in_array($action, ['created', 'updated'], true)) {
                        $c->forceFill([
                            'crm_customer_id' => $r['crm_id'] ?? null,
                            'crm_subscriber_number' => $r['subscriber_number'] ?? null,
                            'crm_sync_status' => $action,
                            'crm_sync_message' => null,
                            'crm_synced_at' => now(),
                        ])->saveQuietly();
                    } else {
                        $c->forceFill([
                            'crm_sync_status' => $action,
                            'crm_sync_message' => $r['message'] ?? null,
                            'crm_synced_at' => now(),
                        ])->saveQuietly();
                    }
                }
            });

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(LegacyCustomer $c): array
    {
        return [
            'pppoe_username' => $c->pppoe_username,
            'customer_type' => $c->customer_type,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'company_title' => $c->company_title,
            'national_id' => $c->national_id,
            'tax_number' => $c->tax_number,
            'tax_office' => $c->tax_office,
            'authorized_first_name' => $c->authorized_first_name,
            'authorized_last_name' => $c->authorized_last_name,
            'authorized_national_id' => $c->authorized_national_id,
            'phone' => $c->phone,
            'phone2' => $c->phone2,
            'email' => $c->email,
            'address' => $c->address,
            'address_building_name' => $c->address_building_name,
            'structured_address_text' => $c->structured_address_text,
            // Aktif paket bağı (CRM ada göre service_package_id + hızı çözer).
            'package_name' => $c->package_name,
            'status' => $c->status,
            'subscription_ends_at' => $c->subscription_ends_at,
            'legacy_id' => $c->legacy_id ?: $c->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toNasPayload(LegacyNas $n): array
    {
        return [
            'name' => $n->name,
            'shortname' => $n->shortname,
            'nas_ip_address' => $n->nas_ip_address,
            'secret' => $n->secret,
            'type' => $n->type,
            'ports' => $n->ports,
            'status' => $n->status,
            'description' => $n->description,
            'api_enabled' => $n->api_enabled,
            'api_host' => $n->api_host,
            'api_port' => $n->api_port,
            'api_username' => $n->api_username,
            'api_password' => $n->api_password,
            'api_tls' => $n->api_tls,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toPackagePayload(LegacyPackage $p): array
    {
        return [
            'name' => $p->name,
            'code' => $p->code,
            'download_rate' => $p->download_rate,
            'upload_rate' => $p->upload_rate,
            'price' => $p->price,
            'currency' => $p->currency,
            'duration_days' => $p->duration_days,
            'duration_type' => $p->duration_type,
            'duration_value' => $p->duration_value,
            'is_active' => $p->is_active,
            'radius_group_name' => $p->radius_group_name,
            'framed_pool' => $p->framed_pool,
            'simultaneous_use' => $p->simultaneous_use,
            'description' => $p->description,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function target(): array
    {
        $base = rtrim((string) config('isp_core_import.base_url'), '/');
        $token = (string) config('isp_core_import.token');

        if ($base === '' || $token === '') {
            throw new RuntimeException('ISP Core hedefi yapılandırılmamış (ISP_CORE_IMPORT_BASE_URL / ISP_CORE_IMPORT_TOKEN).');
        }

        return [$base, $token];
    }
}
