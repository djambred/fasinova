<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Deployer;
use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Health check demo live; berjalan di worker `deployer` (satu-satunya yang memegang socket Docker rootless). */
class HealthSweep implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct() { $this->onQueue('deploy'); }

    public function handle(Deployer $dep): void
    {
        Deployment::where('status', 'live')->with('innovation.track')->each(function (Deployment $d) use ($dep) {
            if (Deployer::healthy($d)) {
                $d->update(['health_failures' => 0, 'last_health_at' => now()]);
                return;
            }
            $d->increment('health_failures');
            $d->update(['last_health_at' => now()]);
            if ($d->health_failures >= 3) {
                $dep->stop($d);
                $i = $d->innovation;
                Notify::innovation([...Notify::pics($i), ...Notify::team($i)], $i, 'Demo dihentikan otomatis',
                    '3 kali health check gagal: '.$i->title, 'danger', '/admin/deployments');
            }
        });
    }
}
