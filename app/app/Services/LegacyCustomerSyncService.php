<?php

namespace App\Services;

use App\Models\LegacyCustomer;
use App\Models\LegacyCustomerPackage;
use App\Models\LegacyCustomerUsage;
use App\Models\LegacyNas;
use App\Models\LegacyPackage;
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

        // Guncel paket/bitis authoritative kaynagi: radusergroup (grup) + radcheck (Expiration).
        // raduserqueue status=1 her musteride isaretli degil (suresi gecmis ama kayitli).
        $groupByUsername = DB::connection('legacy')->table('radusergroup')->get()
            ->filter(fn ($r): bool => filled($r->groupname ?? null))
            ->keyBy('username');
        $expByUsername = DB::connection('legacy')->table('radcheck')
            ->where('attribute', 'Expiration')->get()->keyBy('username');

        // Mevcut müşterileri önden yükle (907 tekil SELECT yerine tek sorgu).
        $existingByLegacyId = LegacyCustomer::where('legacy_source', self::SOURCE)->get()->keyBy('legacy_id');

        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($rows as $u) {
            $legacyId = (string) $u->id;
            $seen[] = $legacyId;

            // Guncel paket = radusergroup grup adi; bitis = radcheck Expiration.
            // Suresi gecmisse gercek (gecmis) tarih gelir -> CRM otomatik 'Expired'.
            $username = $u->username ?: null;
            $packageName = $username ? ($groupByUsername[$username]->groupname ?? null) : null;
            $endsAt = null;
            $rawExp = $username ? ($expByUsername[$username]->value ?? null) : null;
            if (filled($rawExp)) {
                try {
                    $endsAt = \Illuminate\Support\Carbon::parse($rawExp)->toDateTimeString();
                } catch (\Throwable) {
                    $endsAt = null;
                }
            }
            // Fallback: raduserqueue (aktif status=1 ya da en guncel donem).
            if ($packageName === null || $endsAt === null) {
                $queue = $queueByUser[$u->id] ?? collect();
                $active = $queue->firstWhere('status', 1) ?? $queue->sortByDesc('dateTo')->first();
                if ($active) {
                    $packageName ??= $groups[$active->groupId]->name ?? null;
                    $endsAt ??= ($active->dateTo ?: null);
                }
            }

            $isCorporate = filled($u->company ?? null);
            $payload = [
                'pppoe_username' => $u->username ?: null,
                'pppoe_password' => ($u->password ?? '') !== '' ? $u->password : null,
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
        $catalog = $this->syncPackageCatalog($groups);

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'packages' => $packages, 'usage' => $usage, 'catalog' => $catalog, 'skipped_delete' => false];
    }

    /** groupinfo -> legacy_packages (paket kataloğu). Elle düzeltilen (kilitli) alanları EZMEZ. */
    private function syncPackageCatalog(\Illuminate\Support\Collection $groups): int
    {
        $seen = [];

        foreach ($groups as $g) {
            $legacyId = (string) $g->id;
            $seen[] = $legacyId;

            $durType = ($g->expirationUnitType ?? '') !== '' ? $g->expirationUnitType : null;
            $durVal = (int) ($g->expirationUnitValue ?? 0);
            $durDays = match ($durType) {
                'month' => $durVal * 30,
                'week' => $durVal * 7,
                'year' => $durVal * 365,
                'day' => $durVal,
                default => $durVal,
            };

            $payload = [
                'name' => $g->name ?: null,
                'download_rate' => (int) ($g->downrate ?? 0),
                'upload_rate' => (int) ($g->uprate ?? 0),
                'price' => isset($g->price) && $g->price !== '' ? (float) $g->price : null,
                'currency' => 'TRY',
                'duration_days' => $durDays ?: null,
                'duration_type' => $durType,
                'duration_value' => $durVal ?: null,
                'is_active' => true,
                'radius_group_name' => $g->name ?: null,
                'framed_pool' => ($g->groupFramedPool ?? '') !== '' ? $g->groupFramedPool : null,
                'simultaneous_use' => (int) ($g->simUse ?? 0),
                'description' => ($g->note ?? '') !== '' ? $g->note : null,
                'legacy_payload' => (array) $g,
                'legacy_synced_at' => now(),
            ];

            $existing = LegacyPackage::where('legacy_source', self::SOURCE)->where('legacy_id', $legacyId)->first();

            if ($existing) {
                foreach ((array) ($existing->locked_fields ?? []) as $lockedField) {
                    unset($payload[$lockedField]);
                }
                $existing->fill($payload)->save();
            } else {
                $p = new LegacyPackage();
                $p->legacy_source = self::SOURCE;
                $p->legacy_id = $legacyId;
                $p->fill($payload)->save();
            }
        }

        if ($seen !== []) {
            LegacyPackage::where('legacy_source', self::SOURCE)->whereNotIn('legacy_id', $seen)->delete();
        }

        return count($seen);
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

    /**
     * Legacy radius radnas -> legacy_nas (CRM nas_devices tasarımı). Kilitli
     * alanlar korunur, kaynakta olmayanlar silinir. Boş-okuma guard'lı.
     */
    public function syncNasDevices(): int
    {
        $rows = DB::connection('legacy')->table('radnas')->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $seen = [];

        foreach ($rows as $n) {
            $legacyId = (string) $n->id;
            $seen[] = $legacyId;

            $ip = ($n->ip ?? '') !== '' ? $n->ip : null;
            $payload = [
                'name' => ($n->nasName ?? '') !== '' ? $n->nasName : null,
                'shortname' => ($n->nasName ?? '') !== '' ? $n->nasName : null,
                'nas_ip_address' => $ip,
                'secret' => ($n->nasPassword ?? '') !== '' ? $n->nasPassword : null,
                'type' => ($n->type ?? '') !== '' ? $n->type : null,
                'status' => 'active',
                'api_enabled' => (int) ($n->enableApi ?? 0) === 1,
                'api_host' => $ip,
                'api_username' => ($n->apiusername ?? '') !== '' ? $n->apiusername : null,
                'api_password' => ($n->apipassword ?? '') !== '' ? $n->apipassword : null,
                'legacy_payload' => (array) $n,
                'legacy_synced_at' => now(),
            ];

            $existing = LegacyNas::where('legacy_source', self::SOURCE)->where('legacy_id', $legacyId)->first();

            if ($existing) {
                foreach ((array) ($existing->locked_fields ?? []) as $lockedField) {
                    unset($payload[$lockedField]);
                }
                $existing->fill($payload)->save();
            } else {
                $x = new LegacyNas();
                $x->legacy_source = self::SOURCE;
                $x->legacy_id = $legacyId;
                $x->fill($payload)->save();
            }
        }

        if ($seen !== []) {
            LegacyNas::where('legacy_source', self::SOURCE)->whereNotIn('legacy_id', $seen)->delete();
        }

        return count($seen);
    }

}
