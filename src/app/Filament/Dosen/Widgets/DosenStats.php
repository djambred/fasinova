<?php

namespace App\Filament\Dosen\Widgets;

use App\Filament\Dosen\Resources\BimbinganResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DosenStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan';

    protected function getStats(): array
    {
        $u = auth()->user();
        $all = fn () => BimbinganResource::getEloquentQuery();
        $mine = fn () => $all()->where('supervisor_id', $u->id);
        $stats = [
            Stat::make('Perlu persetujuan saya', $mine()->whereNull('supervisor_approved_at')->whereIn('status', ['draft', 'needs_revision'])->count())
                ->color('warning')->icon('heroicon-o-hand-thumb-up'),
            Stat::make('Mahasiswa bimbingan', $mine()->distinct('owner_id')->count('owner_id'))->icon('heroicon-o-users'),
            Stat::make('Karya bimbingan', $mine()->count()),
            Stat::make('Terbit', $mine()->where('status', 'published')->count())->color('success'),
        ];
        if ($u->jabatan()) {
            $stats[] = Stat::make('Karya di lingkup '.$u->jabatan(), $all()->count())->color('info')->icon('heroicon-o-building-library');
        }
        return $stats;
    }
}
