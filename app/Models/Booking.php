<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Concerns\HasDummyVisibility;
use App\Traits\SerializeLocalDates;

class Booking extends Model
{
    use HasFactory, SerializeLocalDates, HasDummyVisibility;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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
        'status',
        'is_dummy',
        'buffer_time',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_dummy' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'reference');
    }
}
