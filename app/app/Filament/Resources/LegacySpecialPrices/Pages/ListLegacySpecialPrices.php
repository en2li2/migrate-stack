<?php

namespace App\Filament\Resources\LegacySpecialPrices\Pages;

use App\Filament\Resources\LegacySpecialPrices\LegacySpecialPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegacySpecialPrices extends ListRecords
{
    protected static string $resource = LegacySpecialPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
