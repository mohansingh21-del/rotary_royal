<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'buffer_time',
        'price',
        'image',
        'status',
    ];

    /**
     * Update asset status based on current availability.
     * 1 for available, 0 for unavailable.
     */
    public static function updateStatusById($id)
    {
        $asset = self::find($id);
        if (!$asset) {
            return;
        }

        $bookedCount = \App\Models\Booking::where('asset_id', $id)
            ->where('status', 'Approved')
            ->count();

        $newStatus = ($bookedCount < $asset->quantity) ? 1 : 0;

        if ($asset->status != $newStatus) {
            $asset->update(['status' => $newStatus]);
        }
    }
}