<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use App\Services\LegacyCustomerSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLegacyCustomers extends ListRecords
{
    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('legacySync')
                ->label('Legacy Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Legacy Sync')
                ->modalDescription('Legacy DB\'den müşteri verileri senkronlanacak. Elle düzelttiğiniz (kilitli) alanlar ve düzenlenmiş adresler KORUNUR.')
                ->modalSubmitActionLabel('Senkronla')
                ->action(function (): void {
                    $r = app(LegacyCustomerSyncService::class)->sync();

                    if ($r['skipped_delete']) {
                        Notification::make()
                            ->title('Aktarım yapılmadı')
                            ->body('Legacy kaynak boş döndü — güvenlik için silme/güncelleme atlandı.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Aktarım tamamlandı')
                        ->body("Eklenen: {$r['created']} · Güncellenen: {$r['updated']} · Silinen: {$r['deleted']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
