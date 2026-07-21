<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendVoucherOrder extends Model
{
    protected $table = 'send_voucher_orders';

    protected $fillable = [
        'order_number', 'customer_id', 'spoc_id', 'spoc_name', 'spoc_email', 'spoc_phone', 'sent_by',
        'proforma_invoice_id', 'tax_invoice_id',
        'total_amount', 'pricing_mode', 'invoice_discount_percentage', 'invoice_discount_amount', 'products_subtotal', 'customer_balance_before', 'customer_balance_after',
        'status', 'email_sent_to', 'sent_at', 'failure_reason',
        'email_attempts', 'total_codes_count', 'codes_hash',
        'delivery_secret_hash', 'delivery_secret_expires_at', 'delivery_secret_used_at', 'delivery_secret_sent_at', 'delivery_secret_sent_to',
        'delivery_otp_hash', 'delivery_otp_expires_at', 'delivery_otp_sent_at', 'delivery_otp_sent_to', 'delivery_otp_verified_at',
        'delivery_downloaded_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'invoice_discount_percentage' => 'decimal:2',
        'invoice_discount_amount' => 'decimal:2',
        'products_subtotal' => 'decimal:2',
        'customer_balance_before' => 'decimal:2',
        'customer_balance_after' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivery_secret_expires_at' => 'datetime',
        'delivery_secret_used_at' => 'datetime',
        'delivery_secret_sent_at' => 'datetime',
        'delivery_otp_expires_at' => 'datetime',
        'delivery_otp_sent_at' => 'datetime',
        'delivery_otp_verified_at' => 'datetime',
        'delivery_downloaded_at' => 'datetime',
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

    public function proformaInvoice()
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function taxInvoice()
    {
        return $this->belongsTo(TaxInvoice::class);
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
