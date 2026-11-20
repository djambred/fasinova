<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

/** Panggil setelah ->id(): ->widgets() membaca cache komponen per id panel (php artisan optimize).
 * Tema Universitas Esa Unggul untuk ketiga panel (admin/PIC, dosen, mahasiswa): satu tempat, sama dengan situs publik. */
class UeuTheme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->colors(['primary' => Color::hex('#0266AF'), 'warning' => Color::hex('#F37021'), 'danger' => Color::Rose, 'success' => Color::Emerald, 'gray' => Color::Slate])
            ->font('Poppins')
            ->brandLogo(fn () => view('filament.brand'))->brandLogoHeight('2rem')
            ->widgets([\App\Filament\Widgets\PanduanAlur::class])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<link rel="stylesheet" href="'.e(asset('css/fasinova-admin.css').'?v='.filemtime(public_path('css/fasinova-admin.css'))).'">');
    }
}
