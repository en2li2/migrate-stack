<?php

namespace App\Filament\Resources\Nas\Pages;

use App\Filament\Resources\Nas\NasResource;
use App\Models\LegacyNas;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNas extends EditRecord
{
    protected static string $resource = NasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Sil'),
        ];
    }

    /**
     * Elle değiştirilen alanları kilitle → Legacy Sync bir daha ezmesin.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locked = (array) ($this->record->locked_fields ?? []);

        foreach (LegacyNas::EDITABLE_FIELDS as $field) {
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
