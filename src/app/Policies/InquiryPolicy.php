<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Inquiry masuk dari publik: admin mengelola, tidak dibuat/dihapus lewat panel. */
class InquiryPolicy extends AdminOnlyPolicy
{
    public function create(User $u): bool { return false; }
    public function delete(User $u, Model $m): bool { return false; }
}
