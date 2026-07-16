<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class VoucherCode extends Model
{
    protected $table = 'voucher_codes';

    protected $fillable = [
        'inventory_id', 'import_log_id', 'xoxoday_order_id', 'product_id',
        'denomination', 'currency_code', 'voucher_code', 'pin', 'validity',
        'status', 'shared_customer_id', 'shared_at',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'validity'     => 'date',
        'shared_at'    => 'datetime',
    ];

    // Never expose raw codes in array/JSON output by default.
    protected $hidden = ['voucher_code', 'pin'];

    /** Encrypt voucher code at rest. */
    protected function voucherCode(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => $v ? Crypt::encryptString($v) : null,
        );
    }

    /** Encrypt pin at rest. */
    protected function pin(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => $v ? Crypt::encryptString($v) : null,
        );
    }

    public function inventory()
    {
        return $this->belongsTo(VoucherInventory::class, 'inventory_id');
    }
}
