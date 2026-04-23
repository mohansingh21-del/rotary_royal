<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'donation_id',
        'receipt_no',
        'transaction_id',
        'status',
        'pdf_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }
}
