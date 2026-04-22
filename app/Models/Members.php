<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Members extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'member_id',
        'address',
        'dob',
        'gender',
        'image',
        'work',
        'date_of_joining',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
