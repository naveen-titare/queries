<?php
// Copy to ~/avirqo-backend/config/send-vouchers.php

return [
    /*
    |--------------------------------------------------------------------------
    | Max codes per email before warning
    |--------------------------------------------------------------------------
    | Gmail 25 MB (~18 MB raw), Outlook 20 MB (~14.5 MB raw)
    | 5000 codes ≈ 2-3 MB Excel, safe
    | 10000 codes ≈ 5-8 MB, near limit
    */
    'max_codes_per_email' => env('SEND_VOUCHER_MAX_CODES', 5000),

    /*
    |--------------------------------------------------------------------------
    | Max attachment MB (raw) before blocking
    |--------------------------------------------------------------------------
    | Safe raw limit accounting for Base64 overhead (33%)
    | 18 MB raw = 24 MB encoded → fits Gmail 25 MB
    | 14 MB raw = 18.6 MB encoded → fits Outlook 20 MB (universal safe)
    */
    'max_attachment_mb' => env('SEND_VOUCHER_MAX_ATTACH_MB', 18),

    /*
    |--------------------------------------------------------------------------
    | Queue enabled?
    |--------------------------------------------------------------------------
    | If true, orders dispatch SendVoucherEmailJob to queue send-vouchers
    | Prevents HTTP timeout for large orders
    */
    'queue_enabled' => env('SEND_VOUCHER_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Secure download threshold
    |--------------------------------------------------------------------------
    | If total codes > this, don't attach Excel, instead upload to S3 and send link
    */
    'secure_link_threshold' => env('SEND_VOUCHER_SECURE_LINK_TH', 5000),
];
