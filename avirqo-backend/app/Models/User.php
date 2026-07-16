<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    // Never exposed in API responses
    protected $hidden = [
        'password',
        'google2fa_secret',
        'pending_google2fa_secret',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'password' => 'hashed',                    // bcrypt, handled automatically by Laravel
        'google2fa_secret' => 'encrypted',           // encrypted at rest in MySQL using APP_KEY
        'pending_google2fa_secret' => 'encrypted',
    ];

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
