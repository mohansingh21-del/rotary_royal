<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'banner_image',
        'date',
        'time',
        'location',
        'latitude',
        'longitude',
        'category_id',
        'description',
        'is_active',
    ];

    public function images()
    {
        return $this->hasMany(EventImage::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
