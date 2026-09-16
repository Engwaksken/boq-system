<?php

namespace App\Console;

use App\Jobs\FetchDailyHardwarePrices;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new FetchDailyHardwarePrices())->dailyAt('06:00')->timezone('Africa/Kampala')->withoutOverlapping();
        $schedule->command('subscriptions:expire')->hourly()->withoutOverlapping();
        $schedule->command('payments:reconcile --hours=48')->everyFiveMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}