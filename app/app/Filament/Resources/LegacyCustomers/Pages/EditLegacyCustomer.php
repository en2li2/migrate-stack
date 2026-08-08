<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegacyCustomer extends EditRecord
{
    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
