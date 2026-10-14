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

        // Provider evidence is refreshed separately from office assessments.
        // The command only writes source snapshots; it never creates a risk
        // level or marks a parcel affected. Hostinger should run schedule:run
        // from its existing cron entry.
        $schedule->command('climate:refresh-sources --provider=open-meteo')
            ->everySixHours()->withoutOverlapping(20)->runInBackground();
        $schedule->command('climate:refresh-sources --provider=chirps')
            ->dailyAt('03:15')->withoutOverlapping(30)->runInBackground();
        if (config('climate.sources.sentinel2.enabled', false)) {
            $schedule->command('climate:refresh-sources --provider=sentinel2')
                ->weeklyOn(1, '04:00')->withoutOverlapping(120)->runInBackground();
        }
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
