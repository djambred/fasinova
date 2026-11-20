<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\InnovationResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Karya saya';

    protected function getStats(): array
    {
        $q = fn () => InnovationResource::getEloquentQuery();
        $due = $q()->where('status', 'needs_revision')->orderBy('revision_due_at')->first();
        $waiting = $q()->where('status', 'submitted')->count();
        return [
            Stat::make('Draf', $q()->where('status', 'draft')->count())->icon('heroicon-o-pencil-square'),
            Stat::make('Menunggu PIC', $waiting)->color($waiting ? 'warning' : 'gray')->icon('heroicon-o-clock'),
            Stat::make('Perlu perbaikan', $q()->where('status', 'needs_revision')->count())->color($due ? 'danger' : 'gray')
                ->description($due?->revision_due_at ? 'Tenggat '.$due->revision_due_at->diffForHumans() : ($due ? 'Revisi lalu kirim ulang' : 'Tidak ada revisi')),
            Stat::make('Disetujui / terbit', $q()->whereIn('status', ['approved', 'published'])->count())->color('success')->icon('heroicon-o-check-badge'),
        ];
    }
}
