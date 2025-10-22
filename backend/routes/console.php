<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule report evaluation to run every hour
Schedule::command('reports:evaluate-schedules')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
