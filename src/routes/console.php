<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('fasinova:health')->everyFiveMinutes();
Schedule::job(new \App\Jobs\CaddySync)->everyFiveMinutes();
Schedule::command('fasinova:overdue')->dailyAt('07:00');
Schedule::call(fn () => \App\Models\DeploymentLog::where('created_at', '<', now()->subDays(30))->delete())->daily();
