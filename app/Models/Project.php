<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Donation;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'banner_image',
        'start_date',
        'end_date',
        'is_funding_available',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_funding_available' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function images()
    {
        return $this->hasMany(ProjectImage::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
