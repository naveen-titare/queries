<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorResetRequest extends Model
{
    protected $fillable = ['token', 'user_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
