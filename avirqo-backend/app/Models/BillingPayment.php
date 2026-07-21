<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model
{
    protected $fillable = [
        'payment_number', 'customer_id', 'proforma_invoice_id', 'status', 'payment_date', 'amount',
        'balance_added_amount', 'credit_note_amount', 'mode', 'reference_no', 'details', 'attachment_path',
        'invalidated_at', 'invalidated_by', 'invalid_reason', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'balance_added_amount' => 'decimal:2',
        'credit_note_amount' => 'decimal:2',
        'invalidated_at' => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function proformaInvoice() { return $this->belongsTo(ProformaInvoice::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
