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
