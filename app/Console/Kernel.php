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
        // Jadwalkan pengiriman notifikasi tagihan setiap hari pukul 08:00
        $schedule->command('app:kirim-tagihan-wa')->dailyAt('08:00');
        // Nonaktifkan pelanggan kedaluwarsa setiap tengah malam (01 menit)
        $schedule->command('app:nonaktifkan-pelanggan')->dailyAt('00:01');
        // Sinkronkan status pelanggan dan sesi aktif MikroTik secara berkala
        $schedule->command('app:sync-mikrotik-sessions')->everyFiveMinutes();
        $schedule->command('app:sync-log-aktivitas')->everyMinute();
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
