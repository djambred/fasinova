<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array { return ['meta' => 'array']; }

    /** Catat siapa melakukan apa; dipanggil dari aksi approve/deploy/stop/env/kuota. */
    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, ?string $subjectId = null, array $meta = []): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'subject_id' => $subjectId, 'meta' => $meta]);
    }
}
