<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Deployment;
use App\Models\Inquiry;
use App\Models\Innovation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** ADM-04 (versi ringkas). PIC hanya melihat angka peminatannya. */
class OverviewStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan';
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $u = auth()->user();
        $scope = fn ($q) => $q->managedBy($u);
        $inn = fn () => $scope(Innovation::query());

        $stats = [
            Stat::make('Menunggu diperiksa', $inn()->where('status', 'submitted')->count())->color('warning')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Perlu perbaikan', $inn()->where('status', 'needs_revision')->count())->color('danger')
                ->description($inn()->where('status', 'needs_revision')->where('revision_due_at', '<', now())->count().' terlambat'),
            Stat::make('Disetujui, belum terbit', $inn()->where('status', 'approved')->count())->color('info'),
            Stat::make('Terbit', $inn()->where('status', 'published')->count())->color('success')->icon('heroicon-o-globe-alt'),
            Stat::make('Demo live', Deployment::where('status', 'live')->whereHas('innovation', fn ($q) => $scope($q))->count())->color('success')->icon('heroicon-o-rocket-launch')
                ->description(Deployment::where('status', 'failed')->whereHas('innovation', fn ($q) => $scope($q))->count().' deploy gagal'),
        ];
        if ($u->isAdmin()) {
            $stats[] = Stat::make('Inquiry baru', Inquiry::where('status', 'new')->count())->color('primary')->icon('heroicon-o-envelope');
        }
        return $stats;
    }
}
