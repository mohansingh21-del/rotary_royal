<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Project;

use App\Traits\SerializeLocalDates;

/**
 * Donation Model tracking financial contributions linked to public Projects.
 * Handles Marquee feature displaying top donors publicly.
 */
class Donation extends Model
{
    use HasFactory, SerializeLocalDates;

    /**
     * Mass assignable attributes handling donation transaction inputs.
     * Contains payment boundaries and public Marquee settings.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'donor_name',
        'mobile_no',
        'amount',
        'date',
        'time',
        'foundation_name',
        'is_marquee',       // Boolean toggle allowing public broadcast of donation
        'payment_receipt',  // Path to external payment verification
    ];

    /**
     * Relationship: The project this financial donation is allocated toward.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Generates a dynamically created strict PDF invoice tied to this specific donation layer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function receipt()
    {
        return $this->hasOne(DonationReceipt::class);
    }
}
