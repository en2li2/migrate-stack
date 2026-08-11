<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\Concerns\NormalizesCustomerFormData;
use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegacyCustomer extends CreateRecord
{
    use NormalizesCustomerFormData;

    protected static string $resource = LegacyCustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeCustomerFormData($data);
    }
}
