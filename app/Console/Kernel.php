<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // This repository has no complete migration history, so the nightly dump is
        // the only way back from a lost database. It runs outside office hours and
        // will not start a second time if the previous one is still writing.
        $schedule->command('db:backup')
            ->dailyAt((string) config('backup.schedule_at', '01:30'))
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
