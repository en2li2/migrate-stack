<?php

namespace App\Services\AddressMatching;

use App\Filament\Forms\Components\StructuredAddressFields;
use App\Models\IspCore\AddressCity;
use App\Models\IspCore\AddressDistrict;
use App\Models\IspCore\AddressNeighborhood;
use App\Models\IspCore\AddressStreet;

/**
 * Legacy serbest-metin adresi → yapılandırılmış öneri (Hatay/UAVT).
 * Mahalle = çıpa: bulununca ilçe türetilir. Eşleşme sırası (güven düşerek):
 * tam normalize → boşluksuz → alias → fuzzy(levenshtein ≤1). "Tahmin yok":
 * fuzzy/İskenderun-önceliği yalnız ÖNERİ olarak işaretlenir, otomatik değil.
 */
class LegacyAddressMatchingEngine
{
    private const MARKERS = ['mahallesi', 'mahalle', 'mah', 'mh'];

    /** Kanıtlı kısaltma/yazım → UAVT normalize karşılığı (bulanıktan ÖNCE uygulanır). */
    private const ALIASES = [
        'm kemal' => 'mustafa kemal',
        'mkemal' => 'mustafa kemal',
        'm.kemal' => 'mustafa kemal',
        'ovunduk' => 'karaagac ovunduk',
        'sarkonak' => 'karaagac sarkkonak',
        'karaagac sarkonak' => 'karaagac sarkkonak',
        'konarli' => 'karaagac konarli',
        'pirereis' => 'pirireis',
        'piri reis' => 'pirireis',
        'barboros' => 'barbaros',
    ];

    private ?int $cityId = null;

    /** @var array<string, array<int, array{id:int,district_id:int,district_name:string,name:string}>> normalize → mahalleler */
    private array $index = [];

    /** @var array<int, string> district_id → ad */
    private array $districtNames = [];

    private ?int $iskenderunId = null;

    public function __construct()
    {
        $city = AddressCity::query()->where('name', 'Hatay')->first()
            ?? AddressCity::query()->where('plate_code', 31)->first();

        if (! $city) {
            return;
        }

        $this->cityId = (int) $city->id;
        $districts = AddressDistrict::query()->where('city_id', $city->id)->get(['id', 'name']);
        foreach ($districts as $d) {
            $this->districtNames[(int) $d->id] = (string) $d->name;
            if (StructuredAddressFields::normalizeName($d->name) === 'iskenderun') {
                $this->iskenderunId = (int) $d->id;
            }
        }

        $nbs = AddressNeighborhood::query()->whereIn('district_id', array_keys($this->districtNames))->get(['id', 'district_id', 'name']);
        foreach ($nbs as $n) {
            $key = StructuredAddressFields::normalizeName($n->name);
            $this->index[$key][] = [
                'id' => (int) $n->id,
                'district_id' => (int) $n->district_id,
                'district_name' => $this->districtNames[(int) $n->district_id] ?? '',
                'name' => (string) $n->name,
            ];
        }
    }

    /**
     * @return array{
     *   status:string, match_type:string, candidate:string, levenshtein:?int,
     *   city_id:?int, district_id:?int, district_name:?string,
     *   neighborhood_id:?int, neighborhood_name:?string,
     *   street_id:?int, street_name:?string,
     *   building_no:?string, apartment_no:?string
     * }
     */
    public function matchAddress(string $raw, bool $withStreet = true): array
    {
        $out = [
            'status' => 'elle-gerekli', 'match_type' => 'yok', 'candidate' => '', 'levenshtein' => null,
            'city_id' => $this->cityId, 'district_id' => null, 'district_name' => null,
            'neighborhood_id' => null, 'neighborhood_name' => null,
            'street_id' => null, 'street_name' => null,
            'building_no' => null, 'apartment_no' => null,
        ];

        $building = $this->parseBuildingParts($raw);
        $out['building_no'] = $building['building_no'];
        $out['apartment_no'] = $building['apartment_no'];

        if ($this->cityId === null) {
            return $out;
        }

        $norm = StructuredAddressFields::normalizeName($raw);
        $candidates = $this->neighborhoodCandidates($norm);
        $out['candidate'] = $candidates[0] ?? '';

        [$hit, $type, $lev] = $this->matchNeighborhood($candidates);

        if ($hit === null) {
            return $out;
        }

        $out['match_type'] = $type;
        $out['levenshtein'] = $lev;
        $out['neighborhood_id'] = $hit['id'];
        $out['neighborhood_name'] = $hit['name'];
        $out['district_id'] = $hit['district_id'];
        $out['district_name'] = $hit['district_name'];

        // Durum: kesin/boşluk/alias + tek-ilçe = otomatik-hazır; aksi öneri
        $out['status'] = in_array($type, ['kesin', 'boşluk', 'alias'], true) && $hit['district_certain']
            ? 'otomatik-hazır'
            : 'öneri-kontrol';

        // Sokak (kuyruk sıralamasında pahalı olduğundan opsiyonel)
        if ($withStreet && $hit['id']) {
            $street = $this->matchStreet($norm, $hit['id']);
            if ($street) {
                $out['street_id'] = $street['id'];
                $out['street_name'] = $street['name'];
            }
        }

        return $out;
    }

    /** Mahalle adayları (marker öncesi 3→1 kelime; yoksa ilk 2 kelime fallback). @return array<int,string> */
    public function neighborhoodCandidates(string $norm): array
    {
        $words = [];
        if (preg_match('/^(.*?)\s+(?:'.implode('|', self::MARKERS).')\b/u', $norm, $m)) {
            $words = preg_split('/\s+/u', trim($m[1])) ?: [];
        } else {
            $words = array_slice(preg_split('/\s+/u', trim($norm)) ?: [], 0, 2);
        }

        $cands = [];
        for ($n = min(3, count($words)); $n >= 1; $n--) {
            $cands[] = implode(' ', array_slice($words, -$n));
        }

        return array_values(array_unique(array_filter($cands)));
    }

    /**
     * @param array<int,string> $candidates
     * @return array{0: ?array{id:int,district_id:int,district_name:string,name:string,district_certain:bool}, 1: string, 2: ?int}
     */
    private function matchNeighborhood(array $candidates): array
    {
        foreach ($candidates as $cand) {
            // 1) tam normalize
            if (isset($this->index[$cand])) {
                return [$this->resolveDistrict($this->index[$cand]), 'kesin', 0];
            }
            // 2) boşluksuz
            $compact = str_replace(' ', '', $cand);
            foreach ($this->index as $key => $list) {
                if (str_replace(' ', '', $key) === $compact) {
                    return [$this->resolveDistrict($list), 'boşluk', 0];
                }
            }
            // 3) alias
            if (isset(self::ALIASES[$cand]) && isset($this->index[self::ALIASES[$cand]])) {
                return [$this->resolveDistrict($this->index[self::ALIASES[$cand]]), 'alias', 0];
            }
        }
        // 4) fuzzy levenshtein ≤1 (en uzun aday üzerinde)
        $cand = $candidates[0] ?? '';
        if ($cand !== '') {
            foreach ($this->index as $key => $list) {
                if (levenshtein($cand, $key) <= 1) {
                    return [$this->resolveDistrict($list), 'bulanık', levenshtein($cand, $key)];
                }
            }
        }

        return [null, 'yok', null];
    }

    /**
     * Aynı isimli mahalle tek ilçedeyse kesin; çoklu + İskenderun varsa İskenderun (öneri); değilse ilk (belirsiz).
     *
     * @param array<int, array{id:int,district_id:int,district_name:string,name:string}> $list
     * @return array{id:int,district_id:int,district_name:string,name:string,district_certain:bool}
     */
    private function resolveDistrict(array $list): array
    {
        $byDistrict = [];
        foreach ($list as $row) {
            $byDistrict[$row['district_id']] = $row;
        }

        if (count($byDistrict) === 1) {
            $row = reset($byDistrict);
            $row['district_certain'] = true;

            return $row;
        }

        if ($this->iskenderunId !== null && isset($byDistrict[$this->iskenderunId])) {
            $row = $byDistrict[$this->iskenderunId];
            $row['district_certain'] = false; // İskenderun önceliği = öneri

            return $row;
        }

        $row = reset($byDistrict);
        $row['district_certain'] = false;

        return $row;
    }

    /** @return array{id:int,name:string}|null */
    public function matchStreet(string $norm, int $neighborhoodId): ?array
    {
        $streets = AddressStreet::query()->where('neighborhood_id', $neighborhoodId)->get(['id', 'name']);
        foreach ($streets as $s) {
            $sn = StructuredAddressFields::normalizeName($s->name);
            if ($sn !== '' && str_contains($norm, $sn)) {
                return ['id' => (int) $s->id, 'name' => (string) $s->name];
            }
        }

        return null;
    }

    /** @return array{building_no:?string, apartment_no:?string} */
    public function parseBuildingParts(string $raw): array
    {
        $no = null;
        $daire = null;

        if (preg_match('/\b(?:no|n)[\s:.]*?(\d+[a-zA-Z\/-]?\d*)/iu', $raw, $m)) {
            $no = trim($m[1], '/-');
        }
        if (preg_match('/\b(?:daire|d|dk|dı?ş\s*kapı)[\s:.]*?(\d+)/iu', $raw, $m)) {
            $daire = $m[1];
        }
        // "748/5" gibi no/daire
        if ($daire === null && preg_match('/\bno[\s:.]*\d+\s*\/\s*(\d+)/iu', $raw, $m)) {
            $daire = $m[1];
        }

        return ['building_no' => $no, 'apartment_no' => $daire];
    }
}
