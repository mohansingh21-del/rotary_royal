<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Run booking expiration + asset left_quantity recalculation every 1 minutes
        $schedule->call(function () {
            app()->make(\App\Services\BookingService::class)->refreshAssetAvailability();
        })->everyMinute()->name('booking-refresh')->withoutOverlapping();

        // Turn off funding availability for completed projects every day
        $schedule->call(function () {
            \App\Models\Project::whereDate('end_date', '<', now()->timezone('Asia/Kolkata')->toDateString())
                ->where('is_funding_available', 1)
                ->update(['is_funding_available' => 0]);
        })->daily()->timezone('Asia/Kolkata')->name('project-funding-expiration')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}