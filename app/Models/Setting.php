<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'bank_name',
        'branch',
        'contact_numbers',
        'email_address',
        'office_address',
        'upi_id',
        'payment_qr_code',
        'marquee_common_message',
        'marquee_items',
    ];

    protected $casts = [
        'marquee_items' => 'json',
    ];
}
