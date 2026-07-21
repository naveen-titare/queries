<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingDocumentEmail extends Model
{
    protected $fillable = ['document_type', 'document_id', 'to_email', 'message', 'sent_by', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function sentBy() { return $this->belongsTo(User::class, 'sent_by'); }
}
