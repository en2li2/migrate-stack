<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('WexConnect Migrate')
            ->colors([
                // WexConnect cyan kimliği (turuncu = sadece kıvılcım, aksiyonda yok)
                'primary' => Color::hex('#0891b2'),
                'gray' => Color::Slate,
                'danger' => Color::hex('#dc2626'),
                'warning' => Color::hex('#ea580c'),
                'success' => Color::hex('#16a34a'),
                'info' => Color::hex('#0891b2'),
            ])
            ->font('Inter')
            ->renderHook('panels::head.end', fn (): string => static::brandStyles())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /** WexConnect stil katmanı — cyan sidebar Option A + buton/form polish (Tailwind derlenmeden, scoped CSS). */
    protected static function brandStyles(): string
    {
        return <<<'HTML'
        <style>
        :root { --wex:#0891b2; --wex-hover:#0e7490; --wex-soft:#ecfeff; --wex-ring:#35c6e6; }

        /* Sidebar aktif öğe — Option A: soft cyan dolgu + 3px sol bar */
        .fi-sidebar-item.fi-active .fi-sidebar-item-button,
        .fi-sidebar-item-active .fi-sidebar-item-button {
            background: var(--wex-soft) !important;
            box-shadow: inset 3px 0 0 0 var(--wex) !important;
            color: var(--wex-hover) !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
        }
        .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
        .fi-sidebar-item-active .fi-sidebar-item-icon { color: var(--wex-hover) !important; }
        .fi-sidebar-item-button:hover { background: #f0fdff; }
        .fi-sidebar-group-label { text-transform: uppercase; letter-spacing:.05em; font-size:11px; color:#94a3b8; }

        /* Butonlar — 8px radius, 500 weight, active scale */
        .fi-btn { border-radius: 8px !important; font-weight: 500 !important; }
        .fi-btn:active { transform: scale(.98); }

        /* Form kontrolleri — cyan focus-ring, temiz kenar */
        .fi-input, .fi-select-input, .fi-fo-field-wrp input, .fi-fo-field-wrp select, .fi-fo-field-wrp textarea {
            border-radius: 8px !important;
        }
        .fi-input:focus, .fi-select-input:focus,
        .fi-fo-field-wrp input:focus, .fi-fo-field-wrp textarea:focus {
            border-color: var(--wex-ring) !important;
            box-shadow: 0 0 0 2px rgba(53,198,230,.35) !important;
        }

        /* Sekme şeridi — aktif sekme cyan alt çizgi */
        .fi-tabs-item.fi-active { color: var(--wex-hover) !important; }
        .fi-tabs-item.fi-active .fi-tabs-item-label { font-weight:600; }

        /* Marka: topbar/başlık cyan aksanı */
        .fi-topbar { border-bottom: 1px solid #e2e8f0; }
        .fi-header-heading { color:#0f172a; }
        </style>
        HTML;
    }
}
