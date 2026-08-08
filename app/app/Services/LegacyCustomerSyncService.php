<?php

namespace App\Services;

use App\Models\LegacyCustomer;
use Illuminate\Support\Facades\DB;

// Legacy DB (userinfo/raduserqueue/groupinfo) -> legacy_customers, idempotent.
// KRİTİK: elle düzeltilen (kilitli) alanları EZMEZ; new_address_text doluysa address'i EZMEZ.
// Kaynak boş dönerse silme ATLANIR (bağlantı arızası veriyi uçurmasın).
class LegacyCustomerSyncService
{
    private const SOURCE = 'proradiusmanager';

    /** @return array{created:int,updated:int,deleted:int,skipped_delete:bool} */
    public function sync(): array
    {
        $rows = DB::connection('legacy')->table('userinfo')->get();

        // Boş-okuma guard'ı
        if ($rows->isEmpty()) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped_delete' => true];
        }

        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($rows as $u) {
            $legacyId = (string) $u->id;
            $seen[] = $legacyId;

            // Aktif abonelik dönemi (status=1) -> paket + bitiş
            $q = DB::connection('legacy')->table('raduserqueue')
                ->where('userId', $u->id)->where('status', 1)
                ->orderByDesc('dateTo')->first();

            $packageName = null;
            $endsAt = null;
            if ($q) {
                $endsAt = $q->dateTo ?: null;
                $g = DB::connection('legacy')->table('groupinfo')->where('id', $q->groupId)->first();
                $packageName = $g->name ?? null;
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

            $existing = LegacyCustomer::where('legacy_source', self::SOURCE)->where('legacy_id', $legacyId)->first();

            if ($existing) {
                // Kilitli alanları payload'dan düş (düzeltmeleri koru)
                foreach ((array) ($existing->locked_fields ?? []) as $lockedField) {
                    unset($payload[$lockedField]);
                }
                // Adres düzenlenmişse legacy address'i EZME
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

        // Kaynakta olmayanları sil (guard geçildi, kaynak dolu)
        $deleted = LegacyCustomer::where('legacy_source', self::SOURCE)
            ->whereNotIn('legacy_id', $seen)
            ->delete();

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'skipped_delete' => false];
    }
}
