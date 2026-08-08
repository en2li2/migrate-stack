<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use App\Services\LegacyCustomerSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLegacyCustomers extends ListRecords
{
    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importLegacy')
                ->label('Legacy DB\'den Aktar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Legacy DB\'den Aktar')
                ->modalDescription('Legacy DB\'den müşteri verileri çekilecek. Elle düzelttiğiniz (kilitli) alanlar ve düzenlenmiş adresler KORUNUR.')
                ->modalSubmitActionLabel('Aktar')
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

            CreateAction::make()->label('Yeni Müşteri'),
        ];
    }
}
