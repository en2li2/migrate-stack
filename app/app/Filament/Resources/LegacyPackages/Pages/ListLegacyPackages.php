<?php

namespace App\Filament\Resources\LegacyPackages\Pages;

use App\Filament\Resources\LegacyPackages\LegacyPackageResource;
use App\Services\LegacyCustomerSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLegacyPackages extends ListRecords
{
    protected static string $resource = LegacyPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('legacySync')
                ->label('Legacy Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Legacy Sync')
                ->modalDescription('Uzak legacy radius DB\'den paket kataloğu (groupinfo) migrate DB\'ye senkronlanacak. Elle düzelttiğiniz (kilitli) alanlar KORUNUR.')
                ->modalSubmitActionLabel('Senkronla')
                ->action(function (): void {
                    $r = app(LegacyCustomerSyncService::class)->sync();

                    if ($r['skipped_delete']) {
                        Notification::make()
                            ->title('Senkron yapılmadı')
                            ->body('Legacy kaynak boş döndü — güvenlik için atlandı.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Legacy Sync tamamlandı')
                        ->body("Paket kataloğu: {$r['catalog']} · Müşteri paketi: {$r['packages']} · Kullanım: {$r['usage']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
