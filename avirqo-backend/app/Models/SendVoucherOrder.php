<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendVoucherOrder extends Model
{
    protected $table = 'send_voucher_orders';

    protected $fillable = [
        'order_number', 'customer_id', 'spoc_id', 'sent_by',
        'total_amount', 'customer_balance_before', 'customer_balance_after',
        'status', 'email_sent_to', 'sent_at', 'failure_reason',
        'email_attempts', 'total_codes_count',
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
        return $this->hasMany(SendVoucherOrderItem::class, 'order_id');
    }

    // Helper for safe order number generation after creation
    public static function generateOrderNumber(int $id): string
    {
        // AVQ-SEND-2026-00001 - uses ID, so no race condition
        // If you want yearly reset, use: year + ID is still unique and safe
        return sprintf('AVQ-SEND-%s-%06d', date('Y'), $id);
        // Alternative ultra-safe: 'AVQ-SEND-' . strtoupper(\Illuminate\Support\Str::ulid())
    }
}
