<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendVoucherOrderItem extends Model
{
    protected $table = 'send_voucher_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'denomination',
        'currency_code', 'quantity', 'total_value',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(SendVoucherOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(SendVoucherProduct::class, 'product_id');
    }

    public function codes()
    {
        return $this->hasMany(SendVoucherCode::class, 'order_item_id');
    }
}
