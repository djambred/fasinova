<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Log audit: hanya dibaca admin, tidak pernah diubah/dihapus (disimpan ≥ 2 tahun). */
class AuditLogPolicy extends AdminOnlyPolicy
{
    public function create(User $u): bool { return false; }
    public function update(User $u, Model $m): bool { return false; }
    public function delete(User $u, Model $m): bool { return false; }
}
