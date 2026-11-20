<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected $guarded = [];
    protected $hidden = ['db_password'];

    protected function casts(): array
    {
        return [
            'allowed_caps' => 'array', 'db_password' => 'encrypted', 'needs_redeploy' => 'boolean', 'upstream_tls' => 'boolean', 'started_at' => 'datetime',
            'finished_at' => 'datetime', 'last_health_at' => 'datetime',
        ];
    }

    public function innovation() { return $this->belongsTo(Innovation::class); }
    public function logs() { return $this->hasMany(DeploymentLog::class); }
    public function history() { return $this->hasMany(DeployHistory::class); }

    /** Deploy sedang dikerjakan worker (bukan sekadar menunggu antrean). */
    public function isRunning(): bool
    {
        return in_array($this->status, ['cloning', 'scanning', 'building', 'starting', 'health_check'], true);
    }

    /** Sudah ada deploy di antrean atau sedang berjalan: tombol (Re)deploy dimatikan. */
    public function isBusy(): bool
    {
        return $this->status === 'queued' || $this->isRunning();
    }

    public function project(): string { return 'fz-'.$this->innovation->slug; }
    public function dir(): string { return rtrim(config('fasinova.apps_dir'), '/').'/'.$this->innovation->slug; }
}
