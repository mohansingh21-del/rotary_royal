<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * Service Layer abstracting intense periodic background capacity constraints.
 * Extracted from controllers to prevent memory bottlenecks during massive scale transactions.
 */
class BookingService
{
    /**
     * Auto-complete expired bookings and recalculate asset left_quantity.
     * Designed to run as a background scheduler job (every 5 minutes).
     */
    public function refreshAssetAvailability(): void
    {
        $now = now();
        $completedCount = 0;
        $assetCount = 0;

        // ── Step 1: Auto-complete expired approved bookings ─────────────────
        // Chunked to avoid memory issues on large datasets.
        Booking::where('status', 'Approved')
            ->with('asset:id,buffer_time')
            ->chunkById(100, function ($bookings) use ($now, &$completedCount) {
                foreach ($bookings as $booking) {
                    if (! $booking->asset) {
                        continue;
                    }

                    $bufferHours    = $booking->buffer_time ?? 0;
                    $completionTime = $booking->end_date->copy()->addHours($bufferHours);

                    if ($now->greaterThan($completionTime)) {
                        $booking->update(['status' => 'Completed']);
                        $completedCount++;
                    }
                }
            });

        // ── Step 2: Recalculate left_quantity for all assets ────────────────
        // Single aggregated query per chunk — no N+1.
        Asset::select('id', 'quantity', 'left_quantity')
            ->chunkById(100, function ($assets) use (&$assetCount) {
                // Fetch approved booking counts for this batch of assets in one query
                $assetIds = $assets->pluck('id');

                $approvedCounts = Booking::selectRaw('asset_id, COUNT(*) as total')
                    ->whereIn('asset_id', $assetIds)
                    ->where('status', 'Approved')
                    ->groupBy('asset_id')
                    ->pluck('total', 'asset_id');

                foreach ($assets as $asset) {
                    $approved            = $approvedCounts->get($asset->id, 0);
                    $newLeftQty          = max(0, $asset->quantity - $approved);

                    if ($asset->left_quantity !== $newLeftQty) {
                        $asset->left_quantity = $newLeftQty;
                        $asset->saveQuietly();
                    }

                    $assetCount++;
                }
            });

        Log::info('[BookingService] Availability refresh completed.', [
            'bookings_completed' => $completedCount,
            'assets_recalculated' => $assetCount,
            'ran_at' => $now->toDateTimeString(),
        ]);
    }
}
