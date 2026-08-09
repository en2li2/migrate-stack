<?php

namespace App\Filament\Resources\LegacyPackages\Pages;

use App\Filament\Resources\LegacyPackages\LegacyPackageResource;
use App\Models\LegacyPackage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegacyPackage extends EditRecord
{
    protected static string $resource = LegacyPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Sil'),
        ];
    }

    /**
     * Elle değiştirilen alanları kilitle → Legacy Sync bir daha ezmesin.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locked = (array) ($this->record->locked_fields ?? []);

        foreach (LegacyPackage::EDITABLE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $new = (string) ($data[$field] ?? '');
            $old = (string) ($this->record->getOriginal($field) ?? '');
            if ($new !== $old) {
                $locked[] = $field;
            }
        }

        $data['locked_fields'] = array_values(array_unique($locked));

        return $data;
    }
}
