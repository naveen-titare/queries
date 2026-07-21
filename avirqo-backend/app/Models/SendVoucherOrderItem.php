<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendVoucherOrderItem extends Model
{
    protected $table = 'send_voucher_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'denomination',
        'currency_code', 'quantity', 'gross_total', 'global_margin_percentage', 'global_margin_amount', 'discount_percentage', 'discount_amount', 'total_value',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'total_value' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'global_margin_percentage' => 'decimal:2',
        'global_margin_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
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
