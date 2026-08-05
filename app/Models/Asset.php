<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\SerializeLocalDates;

class Asset extends Model
{
    use HasFactory, SoftDeletes, SerializeLocalDates;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'left_quantity',
        'buffer_time',
        'price',
        'image',
        'status',
    ];

    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class , 'asset_id');
    }

}
