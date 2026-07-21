<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    protected $fillable = [
        'draft_number', 'pi_number', 'customer_id', 'status', 'issue_date', 'valid_until',
        'discount_type', 'invoice_discount_percentage',
        'subtotal', 'discount_amount', 'taxable_value', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'round_off', 'total_amount', 'paid_amount', 'delivered_amount', 'balance_added_amount',
        'notes', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'invoice_discount_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'balance_added_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(ProformaInvoiceItem::class); }
    public function payments() { return $this->hasMany(BillingPayment::class); }
    public function taxInvoices() { return $this->hasMany(TaxInvoice::class); }
    public function creditNoteApplications() { return $this->hasMany(BillingCreditNoteApplication::class); }

    public function availableForDelivery(): float
    {
        return max(0, (float) $this->balance_added_amount - (float) $this->delivered_amount);
    }
}
