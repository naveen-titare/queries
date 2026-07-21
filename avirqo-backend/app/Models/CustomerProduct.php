<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProduct extends Model
{
    protected $fillable = [
        'customer_id', 'product_id', 'discount_percentage', 'is_blacklisted',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'is_blacklisted' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(SendVoucherProduct::class, 'product_id');
    }
}
