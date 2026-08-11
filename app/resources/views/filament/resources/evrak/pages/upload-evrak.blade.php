<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="display:flex;gap:10px;align-items:center;margin-top:20px;">
            <x-filament::button type="submit" size="lg" icon="heroicon-m-check">
                Kaydet
            </x-filament::button>
            <x-filament::button type="button" color="gray" tag="a" :href="\App\Filament\Resources\Evrak\EvrakResource::getUrl('index')">
                Geri
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>