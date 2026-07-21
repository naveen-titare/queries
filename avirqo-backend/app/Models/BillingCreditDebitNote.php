<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCreditDebitNote extends Model
{
    protected $fillable = [
        'draft_number', 'note_number', 'type', 'customer_id', 'proforma_invoice_id', 'tax_invoice_id',
        'payment_id', 'status', 'amount', 'remaining_amount', 'reason', 'finalized_at', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function proformaInvoice() { return $this->belongsTo(ProformaInvoice::class); }
    public function payment() { return $this->belongsTo(BillingPayment::class, 'payment_id'); }
    public function applications() { return $this->hasMany(BillingCreditNoteApplication::class, 'billing_credit_debit_note_id'); }
}
