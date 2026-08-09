<?php

namespace App\Filament\Resources\Evrak\Pages;

use App\Filament\Resources\Evrak\EvrakResource;
use App\Models\LegacyCustomer;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListEvrak extends ListRecords
{
    protected static string $resource = EvrakResource::class;

    #[Url]
    public ?string $durum = 'bekleyen';

    public function getTitle(): string
    {
        return match ($this->durum) {
            'eksik' => 'Eksik Evraklar',
            'tamamlanan' => 'Tamamlanan Evraklar',
            default => 'Bekleyen Evraklar',
        };
    }

    protected function getTableQuery(): ?Builder
    {
        $query = EvrakResource::getEloquentQuery();
        $any = EvrakResource::anyDocSql();
        $complete = EvrakResource::completeSql();

        return match ($this->durum) {
            'eksik' => $query->whereRaw($any)->whereRaw('NOT '.$complete),
            'tamamlanan' => $query->whereRaw($complete),
            default => $query->whereRaw('NOT '.$any),
        };
    }
}
