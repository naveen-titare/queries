<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorChallenge extends Model
{
    protected $fillable = ['token', 'user_id', 'attempts', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
