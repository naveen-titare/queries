<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherImportLog extends Model
{
    protected $table = 'voucher_import_logs';

    protected $fillable = [
        'user_id', 'product_id', 'brand_name', 'denomination', 'quantity',
        'total_value', 'currency_code', 'status', 'message',
        'xoxoday_order_id', 'po_number', 'request_payload', 'response_payload',
    ];

    protected $casts = [
        'denomination'     => 'decimal:2',
        'total_value'      => 'decimal:2',
        'quantity'         => 'integer',
        'request_payload'  => 'array',
        'response_payload' => 'array',
    ];
}
