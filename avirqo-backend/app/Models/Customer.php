<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name', 'location', 'gst_number',
        'registration_number', 'status', 'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function spocs()
    {
        return $this->hasMany(CustomerSpoc::class);
    }

    public function documents()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function balanceLogs()
    {
        return $this->hasMany(CustomerBalanceLog::class)->latest();
    }

    public function voucherHistory()
    {
        return $this->hasMany(CustomerVoucherHistory::class)->latest('sent_at');
    }
}
