<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherOrderItem extends Model
{
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
        return $this->belongsTo(VoucherOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(VoucherProduct::class, 'product_id');
    }

    public function codes()
    {
        return $this->hasMany(VoucherCode::class, 'order_item_id');
    }
}
