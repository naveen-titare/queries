<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherOrder extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'spoc_id', 'sent_by',
        'total_amount', 'customer_balance_before', 'customer_balance_after',
        'status', 'email_sent_to', 'sent_at', 'failure_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'customer_balance_before' => 'decimal:2',
        'customer_balance_after' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function spoc()
    {
        return $this->belongsTo(CustomerSpoc::class, 'spoc_id');
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function items()
    {
        return $this->hasMany(VoucherOrderItem::class, 'order_id');
    }
}
