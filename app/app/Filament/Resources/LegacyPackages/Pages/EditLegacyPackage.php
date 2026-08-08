<?php

namespace App\Filament\Resources\LegacyPackages\Pages;

use App\Filament\Resources\LegacyPackages\LegacyPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegacyPackage extends EditRecord
{
    protected static string $resource = LegacyPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
