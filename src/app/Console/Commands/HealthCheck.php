<?php

namespace App\Console\Commands;

use App\Jobs\HealthSweep;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'fasinova:health';
    protected $description = 'Jadwalkan health check demo live (dijalankan worker deployer); hentikan setelah 3 kali gagal berturut-turut';

    public function handle(): void
    {
        HealthSweep::dispatch();
    }
}
