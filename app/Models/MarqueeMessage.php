<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarqueeMessage extends Model
{
    use HasFactory;

    protected $fillable = ['marquee_message'];
}
