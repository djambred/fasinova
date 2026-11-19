<?php

namespace App\Policies;

use App\Models\Innovation;
use App\Models\User;

/**
 * Aturan akses karya (PRD §3, §9 Otorisasi):
 * mahasiswa hanya karyanya; PIC hanya peminatannya; deploy/terbit hanya PIC/admin;
 * dosen pembimbing, kaprodi, dan dekan juga PIC untuk bimbingan/lingkupnya.
 */
class InnovationPolicy
{
    public const EDITABLE = ['draft', 'needs_revision'];

    public function viewAny(User $u): bool
    {
        return in_array($u->role, ['admin', 'pic', 'dosen', 'mahasiswa']);
    }

    public function view(User $u, Innovation $i): bool
    {
        return $u->isAdmin() || $this->isPicOf($u, $i) || $this->isSupervisor($u, $i) || $this->inLeadershipScope($u, $i) || $this->isTeam($u, $i);
    }

    public function create(User $u): bool { return $u->role === 'mahasiswa'; }

    public function update(User $u, Innovation $i): bool
    {
        return $u->isAdmin() || ($this->isTeam($u, $i) && in_array($i->status, self::EDITABLE));
    }

    public function delete(User $u, Innovation $i): bool
    {
        return $u->isAdmin() || ($this->isTeam($u, $i) && $i->status === 'draft');
    }

    /** Pembimbing menyetujui karya bimbingannya (sekali). */
    public function approveAsSupervisor(User $u, Innovation $i): bool
    {
        return $this->isSupervisor($u, $i) && ! $i->supervisor_approved_at;
    }

    /** Periksa checklist, setujui/kembalikan/tolak. */
    public function review(User $u, Innovation $i): bool
    {
        return in_array($i->status, ['submitted', 'needs_revision']) && ($u->isAdmin() || $this->isPicOf($u, $i));
    }

    public function markSafe(User $u, Innovation $i): bool { return $this->manage($u, $i); }
    public function deploy(User $u, Innovation $i): bool { return $this->manage($u, $i); }
    public function publish(User $u, Innovation $i): bool { return $this->manage($u, $i); }

    private function manage(User $u, Innovation $i): bool { return $u->isAdmin() || $this->isPicOf($u, $i); }
    private function isPicOf(User $u, Innovation $i): bool { return $u->isPic() && Innovation::whereKey($i->getKey())->managedBy($u)->exists(); }
    private function isSupervisor(User $u, Innovation $i): bool { return $u->role === 'dosen' && $i->supervisor_id === $u->id; }
    private function isTeam(User $u, Innovation $i): bool { return $i->isMember($u); }

    private function inLeadershipScope(User $u, Innovation $i): bool
    {
        return $u->role === 'dosen' && $i->study_program_id && in_array($i->study_program_id, $u->scopeProgramIds());
    }
}
