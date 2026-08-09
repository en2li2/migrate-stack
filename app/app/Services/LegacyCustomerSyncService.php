<?php

namespace App\Services;

use App\Models\LegacyCustomer;
use App\Models\LegacyCustomerPackage;
use App\Models\LegacyCustomerUsage;
use Illuminate\Support\Facades\DB;

// Uzak legacy radius DB (userinfo/raduserqueue/groupinfo/radacct) -> migrate DB.
// Müşteri + paket geçmişi (aktif/bekleyen/geçmiş) + kullanım (oturum) YEREL tablolara yazılır.
// KRİTİK: elle düzeltilen (kilitli) alanları EZMEZ; new_address_text doluysa address'i EZMEZ.
// Kaynak boş dönerse silme ATLANIR (bağlantı arızası veriyi uçurmasın).
class LegacyCustomerSyncService
{
    private const SOURCE = 'proradiusmanager';

    /** @return array{created:int,updated:int,deleted:int,packages:int,usage:int,skipped_delete:bool} */
    public function sync(): array
    {
        // 17.6k paket + ~5k kullanım satırı toplu işlenir; varsayılan 128M yetmez.
        @ini_set('memory_limit', '768M');

        $rows = DB::connection('legacy')->table('userinfo')->get();

        // Boş-okuma guard'ı — kaynak boşsa hiçbir silme/güncelleme yapma.
        if ($rows->isEmpty()) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'packages' => 0, 'usage' => 0, 'skipped_delete' => true];
        }

        // Kaynak referans verileri bir kez yükle (round-trip azalt).
        $groups = DB::connection('legacy')->table('groupinfo')->get()->keyBy('id');
        $queueByUser = DB::connection('legacy')->table('raduserqueue')->get()->groupBy('userId');

        // Mevcut müşterileri önden yükle (907 tekil SELECT yerine tek sorgu).
        $existingByLegacyId = LegacyCustomer::where('legacy_source', self::SOURCE)->get()->keyBy('legacy_id');

        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($rows as $u) {
            $legacyId = (string) $u->id;
            $seen[] = $legacyId;

            // Aktif abonelik dönemi (status=1) -> paket + bitiş
            $active = ($queueByUser[$u->id] ?? collect())->firstWhere('status', 1);
            $packageName = null;
            $endsAt = null;
            if ($active) {
                $endsAt = $active->dateTo ?: null;
                $packageName = $groups[$active->groupId]->name ?? null;
            }

            $isCorporate = filled($u->company ?? null);
            $payload = [
                'pppoe_username' => $u->username ?: null,
                'first_name' => ($u->fname ?? '') !== '' ? $u->fname : null,
                'last_name' => ($u->lname ?? '') !== '' ? $u->lname : null,
                'company_title' => $isCorporate ? $u->company : null,
                'customer_type' => $isCorporate ? 'corporate' : 'individual',
                'national_id' => ($u->jmbg ?? '') !== '' ? $u->jmbg : null,
                'phone' => ($u->mobile ?? '') !== '' ? $u->mobile : (($u->phone ?? '') !== '' ? $u->phone : null),
                'email' => ($u->email ?? '') !== '' ? $u->email : null,
                'address' => ($u->address ?? '') !== '' ? $u->address : null,
                'package_name' => $packageName,
                'subscription_ends_at' => $endsAt,
                'status' => ((int) ($u->enable ?? 0) === 1) ? 'active' : 'suspended',
                'legacy_payload' => (array) $u,
                'legacy_synced_at' => now(),
            ];

            $existing = $existingByLegacyId[$legacyId] ?? null;

            if ($existing) {
                foreach ((array) ($existing->locked_fields ?? []) as $lockedField) {
                    unset($payload[$lockedField]);
                }
                if (filled($existing->new_address_text)) {
                    unset($payload['address']);
                }
                $existing->fill($payload)->save();
                $updated++;
            } else {
                $c = new LegacyCustomer();
                $c->legacy_source = self::SOURCE;
                $c->legacy_id = $legacyId;
                $c->fill($payload)->save();
                $created++;
            }
        }

        // Kaynakta olmayan müşterileri sil (paket/kullanım FK cascade ile gider).
        $deleted = LegacyCustomer::where('legacy_source', self::SOURCE)
            ->whereNotIn('legacy_id', $seen)
            ->delete();

        // Yerel müşteri haritaları (id eşlemesi).
        $customers = LegacyCustomer::where('legacy_source', self::SOURCE)->get(['id', 'legacy_id', 'pppoe_username']);
        $byLegacyId = $customers->keyBy('legacy_id');
        $byUsername = $customers->filter(fn ($c): bool => filled($c->pppoe_username))->keyBy('pppoe_username');

        $packages = $this->syncPackages($queueByUser, $groups, $byLegacyId);
        $usage = $this->syncUsage($byUsername);

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'packages' => $packages, 'usage' => $usage, 'skipped_delete' => false];
    }

    /** raduserqueue -> legacy_customer_packages (bulk upsert + delete-sync). */
    private function syncPackages(\Illuminate\Support\Collection $queueByUser, \Illuminate\Support\Collection $groups, \Illuminate\Support\Collection $byLegacyId): int
    {
        $batch = [];
        $seenIds = [];

        foreach ($queueByUser as $userId => $periods) {
            $cust = $byLegacyId[(string) $userId] ?? null;
            if (! $cust) {
                continue;
            }
            foreach ($periods as $q) {
                $g = $groups[$q->groupId] ?? null;
                $batch[] = [
                    'legacy_customer_id' => $cust->id,
                    'legacy_queue_id' => $q->id,
                    'status_code' => (int) $q->status,
                    'package_name' => $g->name ?? null,
                    'price' => isset($g->price) && $g->price !== '' ? (float) $g->price : null,
                    'starts_at' => $this->dateOrNull($q->dateFrom ?? null),
                    'ends_at' => $this->dateOrNull($q->dateTo ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $seenIds[] = $q->id;
            }
        }

        // Paketler saf ayna — komple tazele (delete-all + bulk insert; whereNotIn yok).
        LegacyCustomerPackage::query()->delete();
        foreach (array_chunk($batch, 1000) as $chunk) {
            LegacyCustomerPackage::insert($chunk);
        }

        return count($seenIds);
    }

    /** radacct -> legacy_customer_usages (bulk upsert + delete-sync). */
    private function syncUsage(\Illuminate\Support\Collection $byUsername): int
    {
        $usernames = $byUsername->keys()->all();
        if ($usernames === []) {
            return 0;
        }

        $batch = [];
        $seenIds = [];

        DB::connection('legacy')->table('radacct')
            ->whereIn('username', $usernames)
            ->orderBy('radacctid')
            ->chunk(3000, function ($chunk) use ($byUsername, &$batch, &$seenIds): void {
                foreach ($chunk as $r) {
                    $cust = $byUsername[$r->username] ?? null;
                    if (! $cust) {
                        continue;
                    }
                    $batch[] = [
                        'legacy_customer_id' => $cust->id,
                        'legacy_radacct_id' => $r->radacctid,
                        'started_at' => $this->dateTimeOrNull($r->acctstarttime ?? null),
                        'stopped_at' => $this->dateTimeOrNull($r->acctstoptime ?? null),
                        'session_time' => (int) ($r->acctsessiontime ?? 0),
                        'download_bytes' => (int) ($r->acctoutputoctets ?? 0),
                        'upload_bytes' => (int) ($r->acctinputoctets ?? 0),
                        'framed_ip' => $r->framedipaddress ?: null,
                        'terminate_cause' => $r->acctterminatecause ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $seenIds[] = $r->radacctid;
                }
            });

        // Kullanım saf ayna — komple tazele (delete-all + bulk insert).
        LegacyCustomerUsage::query()->delete();
        foreach (array_chunk($batch, 1000) as $chunk) {
            LegacyCustomerUsage::insert($chunk);
        }

        return count($seenIds);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        return $value;
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        return $value;
    }
}
