<?php

namespace App\Filament\Resources\Nas\Pages;

use App\Filament\Resources\Nas\NasResource;
use App\Services\LegacyCustomerSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNas extends ListRecords
{
    protected static string $resource = NasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('legacySync')
                ->label('Legacy Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Legacy Sync — NAS')
                ->modalDescription('Uzak legacy radius DB\'den NAS cihazları (radnas) migrate DB\'ye senkronlanacak. Elle düzelttiğiniz (kilitli) alanlar KORUNUR.')
                ->modalSubmitActionLabel('Senkronla')
                ->action(function (): void {
                    $count = app(LegacyCustomerSyncService::class)->syncNasDevices();

                    Notification::make()
                        ->title('Legacy Sync tamamlandı')
                        ->body("NAS cihazı: {$count}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
