<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\SerializeLocalDates;

/**
 * Booking Model representing reservation states for Assets.
 * Tracks reservation lifecycles, UUID assignments, and bounds between members/guests.
 */
class Booking extends Model
{
    use HasFactory, SerializeLocalDates;

    /**
     * The primary key for the model.
     * Driven by UUIDs preventing enumerable sequence guessing.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     * Set to false since Bookings natively consume UUID identifiers.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Mass assignable attributes handling creation footprints via the API endpoint JSON.
     */
    protected $fillable = [
        'id',
        'user_id',
        'asset_id',
        'user_name',
        'user_phone',
        'user_email',
        'start_date',
        'end_date',
        'reference',
        'id_number',
        'id_image_path',
        'payment_image',
        'status',          // Tracks state: (Pending, Approved, Rejected, Completed)
        'buffer_time',     // Cooldown padding before asset can be requested again
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     * Normalizes chronological boundaries.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Relationship: The user who created the Booking.
     * Guest users generate temporary User anchors dynamically bound back here.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: The hardware or generic piece of inventory being requested.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Relationship: Optional Rotarian sponsor approving or linking trust to the Booking.
     * Maps the 'reference' foreign key mapped back to the master User tracking.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'reference');
    }
}
