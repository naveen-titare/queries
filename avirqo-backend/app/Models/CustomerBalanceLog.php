<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBalanceLog extends Model
{
    protected $fillable = [
        'customer_id', 'type', 'amount', 'balance_after', 'note', 'done_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
