<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\DeploymentResource;
use App\Filament\Admin\Resources\InnovationResource;
use App\Filament\Admin\Resources\InquiryResource;
use App\Models\Deployment;
use App\Models\Inquiry;
use App\Models\Innovation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** ADM-04 (versi ringkas). PIC hanya melihat angka peminatannya. Oranye/merah = ada yang perlu ditindak; tiap kartu bisa diklik. */
class OverviewStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan';
    protected ?string $description = 'Angka oranye/merah berarti ada yang perlu Anda tindak lanjuti.';
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $u = auth()->user();
        $inn = fn () => Innovation::query()->managedBy($u);
        $dep = fn (string $s) => Deployment::where('status', $s)->whereHas('innovation', fn ($q) => $q->managedBy($u))->count();
        $list = InnovationResource::getUrl();

        $waiting = $inn()->where('status', 'submitted')->count();
        $revision = $inn()->where('status', 'needs_revision')->count();
        $late = $inn()->where('status', 'needs_revision')->where('revision_due_at', '<', now())->count();
        $approved = $inn()->where('status', 'approved')->count();
        $failed = $dep('failed');

        $stats = [
            Stat::make('Menunggu diperiksa', $waiting)->icon('heroicon-o-inbox-arrow-down')->url($list)
                ->description($waiting ? 'Segera periksa karya baru' : 'Tidak ada antrean')->color($waiting ? 'warning' : 'gray'),
            Stat::make('Perlu perbaikan', $revision)->icon('heroicon-o-arrow-uturn-left')->url($list)
                ->description($late ? "$late lewat tenggat" : 'Menunggu revisi mahasiswa')
                ->descriptionIcon($late ? 'heroicon-m-exclamation-triangle' : null)->color($late ? 'danger' : 'gray'),
            Stat::make('Disetujui, belum terbit', $approved)->icon('heroicon-o-check-badge')->url($list)
                ->description($approved ? 'Menunggu persetujuan publikasi' : 'Tidak ada yang tertunda')->color($approved ? 'warning' : 'gray'),
            Stat::make('Terbit', $inn()->where('status', 'published')->count())->icon('heroicon-o-globe-alt')->url('/')
                ->description('Tampil di website publik')->color('primary'),
            Stat::make('Demo live', $dep('live'))->icon('heroicon-o-rocket-launch')->url(DeploymentResource::getUrl())
                ->description($failed ? "$failed deploy gagal" : 'Semua deploy berjalan')
                ->descriptionIcon($failed ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')->color($failed ? 'danger' : 'success'),
        ];
        if ($u->isAdmin()) {
            $new = Inquiry::where('status', 'new')->count();
            $stats[] = Stat::make('Inquiry baru', $new)->icon('heroicon-o-chat-bubble-left-right')->url(InquiryResource::getUrl())
                ->description($new ? 'Mitra menunggu balasan' : 'Tidak ada pesan baru')->color($new ? 'warning' : 'gray');
        }
        return $stats;
    }
}
