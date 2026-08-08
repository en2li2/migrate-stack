<?php

namespace App\Filament\Resources\LegacySpecialPrices\Pages;

use App\Filament\Resources\LegacySpecialPrices\LegacySpecialPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegacySpecialPrice extends EditRecord
{
    protected static string $resource = LegacySpecialPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
