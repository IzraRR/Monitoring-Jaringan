<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:kirim-tagihan-wa')->dailyAt('08:00');
        $schedule->command('app:nonaktifkan-pelanggan')->dailyAt('00:05');
        $schedule->command('app:sync-mikrotik-sessions')->everyFiveMinutes();
        $schedule->command('app:sync-log-aktivitas')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
