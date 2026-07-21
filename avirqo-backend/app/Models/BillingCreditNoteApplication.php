<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCreditNoteApplication extends Model
{
    protected $fillable = [
        'billing_credit_debit_note_id', 'proforma_invoice_id', 'customer_id', 'amount',
        'status', 'reserved_by', 'reserved_at', 'applied_at', 'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reserved_at' => 'datetime',
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function creditNote() { return $this->belongsTo(BillingCreditDebitNote::class, 'billing_credit_debit_note_id'); }
    public function proformaInvoice() { return $this->belongsTo(ProformaInvoice::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
