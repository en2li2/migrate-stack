<?php

namespace App\Services\AddressMatching;

use App\Models\IspCore\AddressCity;
use App\Models\IspCore\AddressDistrict;
use App\Models\IspCore\AddressNeighborhood;
use App\Models\IspCore\AddressStreet;
use App\Models\LegacyCustomer;
use App\Services\Addresses\StructuredAddressService;
use Illuminate\Support\Facades\Cache;

/**
 * Adres Eşleştirme sihirbazı: adresli+işlenmemiş müşterileri analiz sırasıyla
 * (otomatik-hazır → öneri-kontrol → elle-gerekli) TEK TEK sunar. Öneri kendiliğinden
 * KAYDEDİLMEZ; yalnız operatör onayında new_address_* + new_address_text yazılır.
 */
class AddressMatchingWizardService
{
    private const CACHE_KEY = 'address-wizard:queue:v1';
    private const CACHE_TTL = 3600;

    public function __construct(private readonly LegacyAddressMatchingEngine $engine) {}

    /**
     * İşlenmemiş müşteri kuyruğu: [pppoe => ['id','name','status']] status sıralı.
     * Ağır analiz cache'lenir (sokaksız — hız). new_address_text dolu olan atlanır.
     *
     * @return array<int, array{id:int, pppoe:string, status:string}>
     */
    public function queue(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = [];
            LegacyCustomer::query()
                ->whereNotNull('address')->where('address', '!=', '')
                ->where(fn ($q) => $q->whereNull('new_address_text')->orWhere('new_address_text', ''))
                ->orderBy('id')
                ->chunk(200, function ($chunk) use (&$rows): void {
                    foreach ($chunk as $c) {
                        $m = $this->engine->matchAddress((string) $c->address, withStreet: false);
                        $rows[] = ['id' => (int) $c->id, 'pppoe' => (string) $c->pppoe_username, 'status' => $m['status'], 'name' => (string) $c->name];
                    }
                });

            $order = ['otomatik-hazır' => 0, 'öneri-kontrol' => 1, 'elle-gerekli' => 2];
            usort($rows, fn ($a, $b) => ($order[$a['status']] <=> $order[$b['status']]) ?: strcmp($a['name'], $b['name']));

            return array_map(fn ($r) => ['id' => $r['id'], 'pppoe' => $r['pppoe'], 'status' => $r['status']], $rows);
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sıradaki işlenmemiş müşteri + tam öneri (sokak dahil).
     *
     * @param array<int,string> $skipped
     * @return array{customer: LegacyCustomer, match: array<string,mixed>}|null
     */
    public function next(array $skipped = []): ?array
    {
        $skip = array_flip($skipped);

        foreach ($this->queue() as $row) {
            if (isset($skip[$row['pppoe']])) {
                continue;
            }

            $customer = LegacyCustomer::query()->find($row['id']);
            if ($customer === null || filled($customer->new_address_text)) {
                continue; // arada onaylanmış olabilir
            }

            return ['customer' => $customer, 'match' => $this->engine->matchAddress((string) $customer->address)];
        }

        return null;
    }

    /**
     * @param array<int,string> $skipped
     * @return array{total:int, done:int, remaining:int, skipped:int, groups:array<string,int>}
     */
    public function progress(array $skipped = []): array
    {
        $queue = $this->queue();
        $total = LegacyCustomer::query()->whereNotNull('address')->where('address', '!=', '')->count();
        $groups = ['otomatik-hazır' => 0, 'öneri-kontrol' => 0, 'elle-gerekli' => 0];
        foreach ($queue as $row) {
            if (array_key_exists($row['status'], $groups)) {
                $groups[$row['status']]++;
            }
        }

        return [
            'total' => $total,
            'done' => max(0, $total - count($queue)),
            'remaining' => count($queue),
            'skipped' => count($skipped),
            'groups' => $groups,
        ];
    }

    /**
     * Motor önerisini form state'ine çevirir (öneri forma DOLU gelir).
     *
     * @param array<string,mixed> $match
     * @return array<string,mixed>
     */
    public function formState(array $match): array
    {
        return [
            'new_address_city_id' => $match['city_id'] ?? null,
            'new_address_district_id' => $match['district_id'] ?? null,
            'new_address_neighborhood_id' => $match['neighborhood_id'] ?? null,
            'new_address_street_id' => $match['street_id'] ?? null,
            'new_address_building_no' => $match['building_no'] ?? null,
            'new_address_building_name' => null,
            'new_address_apartment_no' => $match['apartment_no'] ?? null,
            'new_address_note' => null,
        ];
    }

    /**
     * Onay: yapılandırılmış adres müşteri kartına yazılır (+ new_address_text).
     * new_address_text dolunca listede "Düzenlendi" görünür ve sync ezmez.
     *
     * @param array<string,mixed> $data
     */
    public function approve(LegacyCustomer $customer, array $data): void
    {
        $text = app(StructuredAddressService::class)->buildFullAddress($data) ?: null;

        $customer->update([
            'new_address_city_id' => $data['new_address_city_id'] ?? null,
            'new_address_district_id' => $data['new_address_district_id'] ?? null,
            'new_address_neighborhood_id' => $data['new_address_neighborhood_id'] ?? null,
            'new_address_street_id' => $data['new_address_street_id'] ?? null,
            'new_address_building_no' => $data['new_address_building_no'] ?? null,
            'new_address_building_name' => $data['new_address_building_name'] ?? null,
            'new_address_apartment_no' => $data['new_address_apartment_no'] ?? null,
            'new_address_note' => $data['new_address_note'] ?? null,
            'new_address_text' => $text,
        ]);
    }

    /** Öneri kart-üstü bilgisi için okunabilir mahalle/ilçe adları. */
    public function label(array $match): string
    {
        $parts = array_filter([$match['neighborhood_name'] ?? null, $match['district_name'] ?? null]);

        return $parts ? implode(' / ', $parts) : '—';
    }
}
