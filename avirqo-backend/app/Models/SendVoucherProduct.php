<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendVoucherProduct extends Model
{
    protected $table = 'send_voucher_products';

    protected $fillable = [
        'product_id', 'name', 'brand', 'image_url',
        'currency_code', 'currency_name', 'value_type',
        'min_value', 'max_value', 'value_denominations',
        'country_name', 'country_code', 'countries',
        'usage_type', 'delivery_type',
        'terms_and_conditions', 'redemption_instructions', 'expiry_and_validity',
        'order_quantity_limit', 'tat_in_days',
        'fee', 'discount', 'exchange_rate', 'redemption_fee', 'redemption_fee_type',
        'low_stock_threshold', 'is_active',
    ];

    protected $casts = [
        'value_denominations' => 'array',
        'countries' => 'array',
        'is_active' => 'boolean',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'fee' => 'decimal:4',
        'discount' => 'decimal:4',
        'exchange_rate' => 'decimal:4',
    ];

    public function codes()
    {
        return $this->hasMany(SendVoucherCode::class, 'product_id');
    }

    public function availableCodesCount(float $denomination): int
    {
        return $this->codes()
            ->where('denomination', $denomination)
            ->where('status', 'available')
            ->count();
    }

    public function isLowStock(float $denomination): bool
    {
        return $this->availableCodesCount($denomination) <= $this->low_stock_threshold;
    }
}
