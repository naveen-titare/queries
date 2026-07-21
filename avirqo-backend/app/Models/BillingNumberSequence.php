<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingNumberSequence extends Model
{
    protected $fillable = ['document_type', 'financial_year', 'last_number'];
}
