<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 |--------------------------------------------------------------------------
 | Scheduled Tasks
 |--------------------------------------------------------------------------
 |
 | To activate the scheduler, add this cron entry on your server:
 |   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
 |
 */

// Promote sellers between bronze/silver/gold tiers nightly at 02:00
Schedule::command('sellers:promote-tiers')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('sellers:promote-tiers scheduled job failed.');
    });

// Process overdue COD commission invoices — daily at 08:00 Myanmar time (UTC+6:30 = 01:30 UTC)
Schedule::command('cod:process-overdue')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('cod:process-overdue scheduled job failed.');
    });

// Retry failed queue jobs — every hour
Schedule::command('queue:retry all')
    ->hourly()
    ->withoutOverlapping();

// Prune stale cache entries for OTP / idempotency (optional, keeps cache tidy)
Schedule::command('cache:prune-stale-tags')
    ->daily()
    ->onFailure(function () {
        // Non-critical — silently skip if tags not supported
    });