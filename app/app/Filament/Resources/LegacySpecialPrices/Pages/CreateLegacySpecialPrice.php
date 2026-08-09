<?php

namespace App\Filament\Resources\LegacySpecialPrices\Pages;

use App\Filament\Resources\LegacySpecialPrices\LegacySpecialPriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegacySpecialPrice extends CreateRecord
{
    protected static string $resource = LegacySpecialPriceResource::class;

    /** Elle girilen özel fiyat: legacy_source=manual (sync bunları üretmez/dokunmaz). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['legacy_source'] = 'manual';
        $data['legacy_id'] = null;

        return $data;
    }
}
