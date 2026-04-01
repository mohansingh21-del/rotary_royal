<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AutoCompleteBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically marks approved bookings as completed after end_date + buffer_time';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = now();
        $this->info("Checking for bookings to complete at {$now}...");

        $bookings = Booking::where('status', 'Approved')
            ->with('asset')
            ->get();

        $completedCount = 0;

        foreach ($bookings as $booking) {
            $bufferHours = $booking->asset->buffer_time ?? 0;
            $completionDate = Carbon::parse($booking->end_date)->addHours($bufferHours);

            if ($now->greaterThan($completionDate)) {
                $booking->update(['status' => 'Completed']);
                $completedCount++;
                $this->info("Booking {$booking->id} marked as Completed.");
            }
        }

        $this->info("Done! {$completedCount} bookings updated.");

        return Command::SUCCESS;
    }
}