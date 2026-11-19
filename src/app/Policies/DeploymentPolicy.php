<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\Innovation;
use App\Models\User;

/** Deploy hanya PIC peminatan terkait atau admin; tidak ada pembuatan stack manual (PRD §6.1). */
class DeploymentPolicy
{
    public function viewAny(User $u): bool { return $u->isAdmin() || $u->isPic(); }
    public function view(User $u, Deployment $d): bool { return $this->scoped($u, $d); }
    public function create(User $u): bool { return false; }
    public function update(User $u, Deployment $d): bool { return $this->scoped($u, $d); }
    public function delete(User $u, Deployment $d): bool { return $this->scoped($u, $d); }

    private function scoped(User $u, Deployment $d): bool
    {
        return $u->isAdmin() || ($u->isPic() && Innovation::whereKey($d->innovation_id)->managedBy($u)->exists());
    }
}
