<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tombol "Website" di topbar semua panel, di samping lonceng.
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
            fn () => view('filament.website-button'),
        );
        \Illuminate\Support\Facades\Gate::define('manage-settings', fn ($u) => $u->isAdmin());
        //
    }
}
