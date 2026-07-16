<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVoucherHistory extends Model
{
    protected $table = 'customer_voucher_history'; // prevent Laravel auto-pluralising

    protected $fillable = [
        'customer_id', 'voucher_name', 'denomination',
        'quantity', 'total_deducted', 'sent_by', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'denomination' => 'decimal:2',
        'total_deducted' => 'decimal:2',
    ];

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
