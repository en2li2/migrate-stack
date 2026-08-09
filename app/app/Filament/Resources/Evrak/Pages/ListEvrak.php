<?php

namespace App\Filament\Resources\Evrak\Pages;

use App\Filament\Resources\Evrak\EvrakResource;
use App\Models\LegacyCustomer;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvrak extends ListRecords
{
    protected static string $resource = EvrakResource::class;

    public function getTabs(): array
    {
        $any = EvrakResource::anyDocSql();
        $complete = EvrakResource::completeSql();

        return [
            'bekleyen' => Tab::make('Bekleyen Evraklar')
                ->badge(fn (): int => LegacyCustomer::query()->whereRaw('NOT '.$any)->count())
                ->badgeColor('gray')
                ->query(fn (Builder $query): Builder => $query->whereRaw('NOT '.$any)),
            'eksik' => Tab::make('Eksik Evraklar')
                ->badge(fn (): int => LegacyCustomer::query()->whereRaw($any)->whereRaw('NOT '.$complete)->count())
                ->badgeColor('warning')
                ->query(fn (Builder $query): Builder => $query->whereRaw($any)->whereRaw('NOT '.$complete)),
            'tamamlanan' => Tab::make('Tamamlanan Evraklar')
                ->badge(fn (): int => LegacyCustomer::query()->whereRaw($complete)->count())
                ->badgeColor('success')
                ->query(fn (Builder $query): Builder => $query->whereRaw($complete)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'bekleyen';
    }
}
