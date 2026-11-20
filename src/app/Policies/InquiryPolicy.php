<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Inquiry masuk dari publik. Di panel admin: hanya admin yang mengelola (ADM-05).
 * Di panel mahasiswa & dosen: boleh dilihat, karena daftarnya sudah dibatasi ke karya sendiri / bimbingan (lihat InquiryResource).
 * Tidak dibuat/dihapus lewat panel mana pun.
 */
class InquiryPolicy extends AdminOnlyPolicy
{
    public function viewAny(User $u): bool
    {
        return in_array(Filament::getCurrentPanel()?->getId(), ['app', 'dosen'], true) || $u->isAdmin();
    }

    public function create(User $u): bool { return false; }
    public function delete(User $u, Model $m): bool { return false; }
}
