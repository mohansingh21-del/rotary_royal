<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait ProtectsDummyRecords
{
    /**
     * Seeded review records are visible to Super Admins but never editable —
     * they exist only so the store reviewer's screens aren't empty.
     *
     * Returns a 403 response when the record is seeded, null otherwise:
     *
     *     if ($blocked = $this->blockIfDummy($booking)) {
     *         return $blocked;
     *     }
     */
    protected function blockIfDummy(?Model $record, ?string $message = null): ?JsonResponse
    {
        if (!$record || !($record->is_dummy ?? false)) {
            return null;
        }

        return response()->json([
            'status' => 403,
            'message' => $message ?? 'This is a test record used for app store review and cannot be modified.',
        ], 403);
    }
}
