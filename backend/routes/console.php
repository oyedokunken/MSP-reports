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

// Schedule integration sync evaluation to run every 4 hours
Schedule::command('integrations:evaluate-syncs')
    ->everyFourHours()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule data cleanup to run nightly at 2am
Schedule::command('data:cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule alert rule evaluation to run every 5 minutes
Schedule::command('alerts:evaluate')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
