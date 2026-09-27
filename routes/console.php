<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Market location and timezone come from Admin > Settings (App\Support\Regional).
Schedule::command('hardware:fetch-daily --limit=3')
    ->dailyAt('06:00')
    ->timezone(App\Support\Regional::timezone())
    ->withoutOverlapping();

// Works on hosting without a supervisor-managed worker: the per-minute cron drains
// the queue (BOQ generation, pricing jobs). A dedicated `queue:work` process is still
// preferred when available.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('subscriptions:remind')
    ->dailyAt('08:00')
    ->timezone(App\Support\Regional::timezone())
    ->withoutOverlapping();

Schedule::command('payments:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping();
