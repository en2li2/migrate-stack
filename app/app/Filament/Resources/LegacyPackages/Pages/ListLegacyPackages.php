<?php

namespace App\Filament\Resources\LegacyPackages\Pages;

use App\Filament\Resources\LegacyPackages\LegacyPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegacyPackages extends ListRecords
{
    protected static string $resource = LegacyPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
