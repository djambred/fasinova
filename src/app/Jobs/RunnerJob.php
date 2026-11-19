<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RunnerJob implements ShouldQueue
{
    use Queueable;

    // tries 0 + maxExceptions 1: boleh dilepas-ulang saat stack yang sama sedang dikerjakan, tetapi gagal sekali = selesai.
    public int $tries = 0;
    public int $maxExceptions = 1;
    public int $timeout = 1200;

    public function __construct(public int $deploymentId, public string $action)
    {
        $this->onQueue('deploy');
    }

    /** Satu stack dikerjakan satu aksi pada satu waktu (mis. Stop tidak menyela deploy yang sedang berjalan). */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('deployment-'.$this->deploymentId))->releaseAfter(20)->expireAfter(1300)];
    }

    public function handle(Deployer $dep): void
    {
        $d = Deployment::findOrFail($this->deploymentId);
        match ($this->action) {
            'deploy' => $dep->deploy($d),
            'stop' => $dep->stop($d),
            'start' => $dep->start($d),
            'restart' => $dep->restart($d),
            'remove' => $dep->remove($d),
        };
    }
}
