<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule report processing to run every hour
Schedule::command('reports:process-scheduled')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Legacy command (can be removed after migration)
Schedule::command('reports:evaluate-schedules')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule integration sync evaluation to run every 4 hours
// This syncs customer lists only (fast, lightweight)
Schedule::command('integrations:evaluate-syncs')
    ->everyFourHours()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule recent data sync to run daily at 1am
// This pulls last 7 days of data (tickets, devices, etc.) to keep IntegrationData current
Schedule::command('integrations:sync-recent-data --days=7')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule data cleanup to run nightly at 2am
Schedule::command('data:cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule old reports cleanup to run nightly at 3am
Schedule::command('reports:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule alert rule evaluation to run every 5 minutes
Schedule::command('alerts:evaluate')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
