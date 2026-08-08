<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegacyCustomers extends ListRecords
{
    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
