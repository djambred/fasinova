<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\CaddyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Pasang ulang rute Caddy untuk demo live (mis. setelah Caddy restart). Berjalan di worker `deployer` yang memegang socket. */
class CaddySync implements ShouldQueue
{
    use Queueable;

    public function __construct() { $this->onQueue('ops'); }

    public function handle(CaddyClient $caddy): void
    {
        Deployment::where('status', 'live')->each(fn (Deployment $d) => $caddy->ensure($d));
    }
}
