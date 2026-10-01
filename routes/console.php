<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Market location and timezone come from Admin > Settings (App\Support\Regional).
$hardwareScanTime = (string) \App\Models\SiteSetting::get('hardware_auto_scan_time', '06:00');
if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hardwareScanTime)) {
    $hardwareScanTime = '06:00';
}

$hardwareScanLimit = max(1, min(20, (int) \App\Models\SiteSetting::get('hardware_auto_scan_limit', 3)));

Schedule::command('hardware:fetch-daily --limit='.$hardwareScanLimit)
    ->dailyAt($hardwareScanTime)
    ->timezone(App\Support\Regional::timezone())
    ->when(fn (): bool => (bool) \App\Models\SiteSetting::get('hardware_auto_scan_enabled', true))
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

// Warn admins before AI provider credit runs out or expires.
Schedule::command('ai:check-credit')->dailyAt('07:00')->withoutOverlapping();
