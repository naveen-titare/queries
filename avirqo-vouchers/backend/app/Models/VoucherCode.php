<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherCode extends Model
{
    protected $fillable = [
        'product_id', 'denomination', 'currency_code',
        'code_encrypted', 'pin_encrypted',
        'expiry_date', 'status', 'order_item_id',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'expiry_date' => 'date',
        // Laravel's 'encrypted' cast uses APP_KEY to encrypt/decrypt transparently
        'code_encrypted' => 'encrypted',
        'pin_encrypted' => 'encrypted',
    ];

    // Never expose encrypted fields in API responses
    protected $hidden = ['code_encrypted', 'pin_encrypted'];

    public function product()
    {
        return $this->belongsTo(VoucherProduct::class, 'product_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(VoucherOrderItem::class, 'order_item_id');
    }

    // Safe accessor — only call this at send time, never in list queries
    public function getDecryptedCode(): string
    {
        return $this->code_encrypted; // cast handles decryption
    }

    public function getDecryptedPin(): ?string
    {
        return $this->pin_encrypted;
    }
}
