<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'fasinova:health';
    protected $description = 'Health check demo live; hentikan setelah 3 kali gagal berturut-turut';

    public function handle(Deployer $dep): void
    {
        Deployment::where('status', 'live')->each(function (Deployment $d) use ($dep) {
            if (Deployer::healthy($d)) {
                $d->update(['health_failures' => 0, 'last_health_at' => now()]);
                return;
            }
            $d->increment('health_failures');
            $d->update(['last_health_at' => now()]);
            if ($d->health_failures >= 3) {
                $dep->stop($d);
                $i = $d->innovation;
                \App\Support\Notify::innovation([...\App\Support\Notify::pics($i), ...\App\Support\Notify::team($i)], $i, 'Demo dihentikan otomatis',
                    '3 kali health check gagal: '.$i->title, 'danger', '/admin/deployments');
            }
        });
    }
}
