<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Project;

use App\Traits\SerializeLocalDates;

class Donation extends Model
{
    use HasFactory, SerializeLocalDates;

    protected $fillable = [
        'project_id',
        'donor_name',
        'mobile_no',
        'amount',
        'date',
        'foundation_name',
        'is_marquee',
        'payment_receipt',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
