<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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

class BranchPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('branch')
            ->path('branch')
            ->login()
            ->colors([
                'primary' => Color::generateV3Palette('#A32235'),
                'warning' => Color::generateV3Palette('#C8961E'),
            ])
            ->font('Tajawal')
            ->brandName('ذبائح بلادي')
            ->brandLogo(asset('brand/logo.svg'))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('brand/logo-mark.svg'))
            ->sidebarCollapsibleOnDesktop()
            ->resources([
                \App\Filament\Resources\Orders\OrderResource::class,
                \App\Filament\Resources\Products\ProductResource::class,
                \App\Filament\Resources\DeliverySlots\DeliverySlotResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Branch/Pages'), for: 'App\Filament\Branch\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Branch/Widgets'), for: 'App\Filament\Branch\Widgets')
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
}
