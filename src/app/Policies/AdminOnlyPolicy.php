<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Dasar untuk data master & sistem: hanya admin. Policy turunan mengikuti nama model (auto-discovery Laravel). */
abstract class AdminOnlyPolicy
{
    public function viewAny(User $u): bool { return $u->isAdmin(); }
    public function view(User $u, Model $m): bool { return $u->isAdmin(); }
    public function create(User $u): bool { return $u->isAdmin(); }
    public function update(User $u, Model $m): bool { return $u->isAdmin(); }
    public function delete(User $u, Model $m): bool { return $u->isAdmin(); }
}
