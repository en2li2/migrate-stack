<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make("stageCrmPanel")
                ->label("Stage-Crm-Panel Aktar")
                ->icon("heroicon-o-arrow-top-right-on-square")
                ->color("primary")
                ->url("https://panel.wexconnect.com.tr", shouldOpenInNewTab: true),
        ];
    }
}
