<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxInvoiceItem extends Model
{
    protected $fillable = [
        'tax_invoice_id', 'product_id', 'brand', 'product_name', 'hsn_sac', 'currency_code',
        'denomination', 'quantity', 'unit_price', 'discount_percentage', 'discount_amount',
        'taxable_value', 'gst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'line_total',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function taxInvoice() { return $this->belongsTo(TaxInvoice::class); }
    public function product() { return $this->belongsTo(SendVoucherProduct::class, 'product_id'); }
}
