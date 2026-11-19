<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\PanelProvider;
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
        return $panel->id('admin')->path('admin')->login(\App\Filament\Pages\PanelLoginRedirect::class)->profile()->databaseNotifications()->databaseNotificationsPolling('30s')
            ->brandName('FASINOVA · PIC & Admin')
            ->colors(['primary' => Color::hex('#1AA5BA')])->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Pemeriksaan'), NavigationGroup::make('Deploy'),
                NavigationGroup::make('Master data'), NavigationGroup::make('Sistem')->collapsed(),
            ])
            ->pages([Dashboard::class])
            ->widgets([\App\Filament\Admin\Widgets\OverviewStats::class])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, AuthenticateSession::class,
                ShareErrorsFromSession::class, PreventRequestForgery::class, SubstituteBindings::class,
                DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class]);
    }
}
