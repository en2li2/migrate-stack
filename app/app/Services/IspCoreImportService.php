<?php

namespace App\Services;

use App\Models\LegacyCustomer;
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
     * @return array<string, int>
     */
    public function pushCustomers(bool $dryRun = false): array
    {
        $base = rtrim((string) config('isp_core_import.base_url'), '/');
        $token = (string) config('isp_core_import.token');
        $batchSize = max(1, (int) config('isp_core_import.batch_size', 100));

        if ($base === '' || $token === '') {
            throw new RuntimeException('ISP Core hedefi yapılandırılmamış (ISP_CORE_IMPORT_BASE_URL / ISP_CORE_IMPORT_TOKEN).');
        }

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
            'legacy_id' => $c->legacy_id ?: $c->id,
        ];
    }
}
