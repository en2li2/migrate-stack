<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegacyCustomer extends CreateRecord
{
    protected static string $resource = LegacyCustomerResource::class;
}
