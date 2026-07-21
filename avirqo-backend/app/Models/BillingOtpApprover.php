<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingOtpApprover extends Model
{
    protected $fillable = ['group_key', 'label', 'emails', 'is_active'];

    protected $casts = [
        'emails' => 'array',
        'is_active' => 'boolean',
    ];
}
