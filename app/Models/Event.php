<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Event Model defining scheduled Rotary-sponsored community gatherings.
 * Bound directly to descriptive categories and strict geographical coordinates.
 */
class Event extends Model
{
    use HasFactory;

    /**
     * Mass assignable scheduling attributes.
     * Contains locational mapping data for mobile app pins.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'banner_image',
        'date',
        'time',
        'location',
        'latitude',      // GIS coordinate for Map plotting
        'longitude',     // GIS coordinate for Map plotting
        'category_id',
        'description',
        'is_active',
    ];

    /**
     * Relationship: An Event scales to holding multiple gallery promotional Images.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(EventImage::class);
    }

    /**
     * Relationship: Defines the generic type/genre classification of this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
