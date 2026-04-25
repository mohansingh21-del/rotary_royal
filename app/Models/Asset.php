<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\SerializeLocalDates;

/**
 * Asset Model representing physical inventory available for User/Guest Bookings.
 * Actively monitors stock depletion (quantity vs left_quantity) dynamically via cron constraints.
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes, SerializeLocalDates;

    /**
     * Mass assignable inventory attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'quantity',         // Absolute total inventory purchased
        'left_quantity',    // Real-time stock available for renting
        'buffer_time',      // Wait interval required between bookings for checkout
        'price',
        'image',
        'status',           // 1 for active, 0 for hidden/disabled
    ];

    /**
     * Relationship: Comprehensive ledger of all Bookings holding this generic Asset.
     * Used exclusively to calculate concurrent overlaps.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class, 'asset_id');
    }
}