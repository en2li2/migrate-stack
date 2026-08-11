<?php

namespace App\Filament\Resources\LegacyCustomers\Concerns;

use App\Services\Addresses\StructuredAddressService;

/**
 * Müşteri formu kaydetme normalizasyonu (Create+Edit ortak):
 *  - yapılandırılmış adres seçildiyse new_address_text metnini üret
 *    (bu, listedeki "Düzenlendi" rozeti + sync koruması sinyalidir),
 *  - fatura kesim moduna göre ilgisiz parametreyi sıfırla.
 */
trait NormalizesCustomerFormData
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalizeCustomerFormData(array $data): array
    {
        if (filled($data['new_address_neighborhood_id'] ?? null) || filled($data['new_address_city_id'] ?? null)) {
            $data['new_address_text'] = app(StructuredAddressService::class)->buildFullAddress($data) ?: null;
        }

        $mode = $data['invoice_timing_mode'] ?? null;
        if ($mode !== 'delayed') {
            $data['invoice_timing_grace_hours'] = null;
        }
        if ($mode !== 'advance') {
            $data['invoice_timing_advance_days'] = null;
        }
        if (blank($mode)) {
            $data['invoice_timing_mode'] = null;
        }

        return $data;
    }
}
