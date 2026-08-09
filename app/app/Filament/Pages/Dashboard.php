<?php

namespace App\Filament\Pages;

use App\Services\IspCoreImportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Throwable;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ispCoreImport')
                ->label('Stage-Crm-Panel Aktar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Stage CRM paneline aktar')
                ->modalDescription('Migrate müşterileri stage CRM paneline aktarılacak. Eşleşme pppoe ile: mevcutlar güncellenir (abone no korunur), yeniler oluşturulur.')
                ->modalSubmitActionLabel('Aktar')
                ->action(function (): void {
                    try {
                        $r = app(IspCoreImportService::class)->pushCustomers(false);

                        Notification::make()
                            ->title('Aktarım tamamlandı')
                            ->body("Toplam {$r['total']} · Oluşturulan {$r['created']} · Güncellenen {$r['updated']} · Hata {$r['error']}")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Aktarım hatası')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('stageCrmPanelLink')
                ->label('Panele Git')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url('https://panel.wexconnect.com.tr', shouldOpenInNewTab: true),
        ];
    }
}
