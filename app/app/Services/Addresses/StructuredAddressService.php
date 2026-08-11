<?php

namespace App\Services\Addresses;

use App\Models\IspCore\AddressCity;
use App\Models\IspCore\AddressDistrict;
use App\Models\IspCore\AddressNeighborhood;
use App\Models\IspCore\AddressStreet;
use Illuminate\Support\Str;

/**
 * ISP Core'dan port — migrate panelinde yapılandırılmış adres yardımcıları.
 * Address* modelleri isp_panel DB'sine (SALT-OKUR) bağlıdır.
 */
class StructuredAddressService
{
    public function defaultCity(): ?AddressCity
    {
        return AddressCity::query()->where('name', 'Hatay')->first()
            ?? AddressCity::query()->where('plate_code', 31)->first();
    }

    public function normalizeName(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return Str::title(Str::lower($value));
    }

    /**
     * Tam adres metni: "X Mah. Y Sk. No: 5 Bina Daire: 3 not, İlçe/İl".
     * new_address_* alan adlarını da kabul eder (migrate legacy_customers).
     *
     * @param array<string, mixed> $data
     */
    public function buildFullAddress(array $data): string
    {
        $pick = fn (string $a, string $b) => $data[$a] ?? $data[$b] ?? null;

        $cityId = $pick('new_address_city_id', 'address_city_id');
        $districtId = $pick('new_address_district_id', 'address_district_id');
        $neighborhoodId = $pick('new_address_neighborhood_id', 'address_neighborhood_id');
        $streetId = $pick('new_address_street_id', 'address_street_id');
        $buildingName = $pick('new_address_building_name', 'address_building_name');
        $buildingNo = $pick('new_address_building_no', 'address_building_no');
        $apartmentNo = $pick('new_address_apartment_no', 'address_apartment_no');
        $note = $pick('new_address_note', 'address_note');

        $city = filled($cityId) ? AddressCity::query()->find($cityId) : null;
        $district = filled($districtId) ? AddressDistrict::query()->find($districtId) : null;
        $neighborhood = filled($neighborhoodId) ? AddressNeighborhood::query()->find($neighborhoodId) : null;
        $street = filled($streetId) ? AddressStreet::query()->find($streetId) : null;

        $parts = [];
        if ($neighborhood) {
            $parts[] = $this->suffix($neighborhood->name, 'Mah.');
        }
        if ($street) {
            $parts[] = $this->suffix($street->name, 'Sk.');
        }
        if (filled($buildingNo)) {
            $parts[] = 'No: '.trim((string) $buildingNo);
        }
        if (filled($buildingName)) {
            $parts[] = trim((string) $buildingName);
        }
        if (filled($apartmentNo)) {
            $parts[] = 'Daire: '.trim((string) $apartmentNo);
        }
        if (filled($note)) {
            $parts[] = trim((string) $note);
        }

        $location = trim(($district?->name ?: '').($city ? '/'.$city->name : ''), '/');

        return trim(implode(' ', array_filter($parts)).($location ? ', '.$location : ''));
    }

    private function suffix(string $value, string $suffix): string
    {
        $value = trim($value);
        $lower = Str::lower($value);

        return Str::contains($lower, ['mah.', 'mahallesi', 'sokak', 'sk.', 'cadde', 'caddesi', 'bulvar'])
            ? $value
            : trim($value.' '.$suffix);
    }
}
