<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherInventory extends Model
{
    protected $table = 'voucher_inventory';

    protected $fillable = [
        'product_id', 'brand_name', 'image_url', 'currency_code',
        'denomination', 'quantity_imported', 'quantity_shared', 'quantity_available',
    ];

    protected $casts = [
        'denomination'       => 'decimal:2',
        'quantity_imported'  => 'integer',
        'quantity_shared'    => 'integer',
        'quantity_available' => 'integer',
    ];

    public function codes()
    {
        return $this->hasMany(VoucherCode::class, 'inventory_id');
    }

    /** Total value held for this denomination row. */
    public function getTotalValueAttribute(): float
    {
        return (float) $this->denomination * $this->quantity_available;
    }

    /** Increase stock after a successful import. */
    public function addStock(int $qty): void
    {
        $this->quantity_imported += $qty;
        $this->quantity_available += $qty;
        $this->save();
    }
}
