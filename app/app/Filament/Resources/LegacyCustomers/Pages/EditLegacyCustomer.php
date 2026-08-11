<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\Concerns\NormalizesCustomerFormData;
use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegacyCustomer extends EditRecord
{
    use NormalizesCustomerFormData;

    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeCustomerFormData($data);
    }
}
