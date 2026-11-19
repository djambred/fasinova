<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login;

/** Panel tidak punya halaman login sendiri; semua diarahkan ke login tunggal /masuk. */
class PanelLoginRedirect extends Login
{
    public function mount(): void
    {
        $this->redirect(route('login'), navigate: false);
    }
}
