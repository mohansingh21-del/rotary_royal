<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Concerns\HasDummyFlag;
use App\Traits\SerializeLocalDates;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, SerializeLocalDates, HasDummyFlag;

    /**
     * The attributes that are mass assignable.
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
        'is_dummy',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_dummy' => 'boolean',
    ];

    /**
     * The review account's address is a placeholder that nothing can deliver
     * to. Returning no route makes the mail channel skip it, so a store
     * reviewer's actions never queue outbound mail — the database channel
     * still records the notification, so their in-app list stays populated.
     */
    public function routeNotificationForMail($notification)
    {
        return $this->is_dummy ? null : $this->email;
    }

    public function member()
    {
        return $this->hasOne(Members::class);
    }

    public function donationReceipts()
    {
        return $this->hasMany(DonationReceipt::class);
    }
}
