<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunnerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(public int $deploymentId, public string $action)
    {
        $this->onQueue('deploy');
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
