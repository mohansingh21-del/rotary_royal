<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

use App\Traits\SerializeLocalDates;

/**
 * Core User Model representing authenticated users, guest users, and administrative accounts.
 * Enforces role-based bindings and handles standard Laravel authentication tokens.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, SerializeLocalDates;

    /**
     * The attributes that are mass assignable.
     * These bindings securely allow mass-creation using Eloquent arrays.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Prevents sensitive tokens and hashes from leaking into JSON API responses.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     * Automatically formats timestamps to strict Date/Time components upon retrieval.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Relationship: An authenticated User (with 'Member' role) has one exact Member profile entity.
     * Connects core authentication data to localized Club tracking data.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne(Members::class);
    }

    /**
     * Relationship: A User can possess multiple Donation Receipts.
     * Associates generated PDF invoices dynamically mapping contribution values.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function donationReceipts()
    {
        return $this->hasMany(DonationReceipt::class);
    }
}
