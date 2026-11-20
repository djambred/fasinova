<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\DeploymentResource;
use App\Filament\Admin\Resources\InnovationResource as PicInnovations;
use App\Filament\App\Resources\InnovationResource as MyInnovations;
use App\Filament\Dosen\Resources\BimbinganResource;
use App\Models\Deployment;
use App\Models\Inquiry;
use App\Models\Innovation;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/** Sambutan + alur 5 tahap di dasbor tiap panel. Angka memakai cakupan yang sama dengan daftar di panel itu. */
class PanduanAlur extends Widget
{
    protected string $view = 'filament.widgets.panduan-alur';
    protected static ?int $sort = -10;
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = '30s';

    protected function getViewData(): array
    {
        $u = auth()->user();
        // Deploy dengan status tertentu, dibatasi ke karya yang terlihat di panel ini ($q).
        $q = null;   // diisi per panel di bawah; closure memegang referensinya
        $dep = function (string $status) use (&$q) {
            return Deployment::where('status', $status)->whereIn('innovation_id', $q()->select('innovations.id'))->count();
        };

        switch (Filament::getCurrentPanel()?->getId()) {
            case 'app':
                $q = fn () => MyInnovations::getEloquentQuery();
                $url = MyInnovations::getUrl();
                $steps = [
                    ['Draf', $q()->where('status', 'draft')->count(), 'Lengkapi lalu minta persetujuan pembimbing.', $url, true],
                    ['Menunggu PIC', $q()->where('status', 'submitted')->count(), 'Sedang diperiksa PIC peminatan.', $url, false],
                    ['Perlu perbaikan', $q()->where('status', 'needs_revision')->count(), 'Revisi sesuai catatan PIC, lalu kirim ulang.', $url, true],
                    ['Terbit', $q()->where('status', 'published')->count(), 'Sudah tampil di halaman publik FASINOVA.', '/', false],
                    ['Demo live', $dep('live'), 'Aplikasi berjalan & bisa dicoba pengunjung.', $url, false],
                ];
                $intro = 'Ini perjalanan karya Anda di FASINOVA. Klik salah satu tahap untuk membuka karyanya.';
                break;
            case 'dosen':
                $q = fn () => BimbinganResource::getEloquentQuery()->where('supervisor_id', $u->id);
                $url = BimbinganResource::getUrl();
                $steps = [
                    ['Perlu persetujuan', $q()->whereNull('supervisor_approved_at')->whereIn('status', ['draft', 'needs_revision'])->count(), 'Karya bimbingan menunggu persetujuan Anda.', $url, true],
                    ['Menunggu PIC', $q()->where('status', 'submitted')->count(), 'Sudah Anda setujui, sedang diperiksa PIC.', $url, false],
                    ['Perlu perbaikan', $q()->where('status', 'needs_revision')->count(), 'Dikembalikan PIC ke mahasiswa untuk direvisi.', $url, false],
                    ['Terbit', $q()->where('status', 'published')->count(), 'Sudah tampil di halaman publik FASINOVA.', '/', false],
                    ['Demo live', $dep('live'), 'Aplikasi bimbingan yang bisa dicoba pengunjung.', $url, false],
                ];
                $intro = 'Ini ringkasan karya mahasiswa bimbingan Anda. Klik salah satu tahap untuk membuka datanya.';
                break;
            default: // admin & PIC: hanya peminatan yang dikelola
                $q = fn () => Innovation::query()->managedBy($u);
                $url = PicInnovations::getUrl();
                $steps = [
                    ['Menunggu diperiksa', $q()->where('status', 'submitted')->count(), 'Karya baru dari mahasiswa. Periksa kelengkapan & kualitas.', $url, true],
                    ['Perlu perbaikan', $q()->where('status', 'needs_revision')->count(), 'Dikembalikan ke mahasiswa untuk direvisi.', $url, false],
                    ['Disetujui', $q()->where('status', 'approved')->count(), 'Lolos periksa, menunggu persetujuan publikasi.', $url, true],
                    ['Terbit', $q()->where('status', 'published')->count(), 'Sudah tampil di halaman publik FASINOVA.', '/', false],
                    ['Demo live', $dep('live'), 'Aplikasi berjalan & bisa dicoba pengunjung.', DeploymentResource::getUrl(), false],
                ];
                $intro = 'Ini ringkasan alur karya mahasiswa di FASINOVA. Klik salah satu tahap untuk langsung membuka datanya.';
                $extra = $dep('failed') + ($u->isAdmin() ? Inquiry::where('status', 'new')->count() : 0);
        }

        // "Perlu tindakan" = tahap yang menunggu pengguna ini sendiri (bukan menunggu pihak lain).
        $todo = collect($steps)->where(4, true)->sum(1) + ($extra ?? 0);

        return ['nama' => $u->name, 'intro' => $intro, 'steps' => $steps, 'todo' => $todo, 'todoUrl' => $url];
    }
}
