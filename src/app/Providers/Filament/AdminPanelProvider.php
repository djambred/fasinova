<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
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
        return UeuTheme::apply($panel->id('admin'))->path('admin')->login(\App\Filament\Pages\PanelLoginRedirect::class)->profile()
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make('dosen')->label('Dasbor Dosen')->url('/dosen')->icon('heroicon-o-academic-cap')
                    ->visible(fn () => in_array(auth()->user()?->role, ['dosen', 'pic', 'admin'])),
                \Filament\Navigation\MenuItem::make('mahasiswa')->label('Panel Mahasiswa')->url('/app')->icon('heroicon-o-rocket-launch')
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])->databaseNotifications()->databaseNotificationsPolling('30s')
            ->favicon(asset('icon.svg'))->brandName('FASINOVA · PIC & Admin')
            ->sidebarCollapsibleOnDesktop()
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
