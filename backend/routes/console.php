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

// Smart Monitor: Handles Auto-Start, Stale Checks, and Schedules
Schedule::command('integrations:monitor')
    ->everyMinute()
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

// Cleanup stale syncs (stuck in 'running' for >2hrs) to prevent scheduler blockage
Schedule::command('syncs:cleanup-stale')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Sync Autotask resources (employees) monthly on the 1st at 4am
// Resources change infrequently (hiring/firing), so monthly sync is sufficient
// Uses historical import to refresh the 45 employee records
Schedule::command('integrations:import-historical 1 --resources-only')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->runInBackground();
// Schedule audit log cleanup to run nightly at 4:30am
Schedule::command('audit:cleanup 90')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground();
