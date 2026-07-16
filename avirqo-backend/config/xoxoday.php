<?php

/**
 * Xoxoday (Plum Pro) API configuration.
 * All secrets come from .env — never hard-code them.
 *
 * Add to your Laravel .env:
 *   XOXODAY_BASE_URL=https://stagingstores.xoxoday.com   (prod: https://accounts.xoxoday.com)
 *   XOXODAY_ACCESS_TOKEN=xxxx
 *   XOXODAY_DEFAULT_COUNTRY=IN
 *   XOXODAY_ORDER_EMAIL=orders@avirqo.in
 *   XOXODAY_ORDER_CONTACT=+91-0000000000
 */
return [
    'base_url'      => rtrim(env('XOXODAY_BASE_URL', 'https://stagingstores.xoxoday.com'), '/'),
    'api_path'      => env('XOXODAY_API_PATH', '/chef/v1/oauth/api'),

    'client_id'     => env('XOXODAY_CLIENT_ID'),
    'client_secret' => env('XOXODAY_CLIENT_SECRET'),
    'access_token'  => env('XOXODAY_ACCESS_TOKEN'),
    'refresh_token' => env('XOXODAY_REFRESH_TOKEN'),

    'tag'             => env('XOXODAY_TAG', 'plumProAPI'),
    'default_country' => env('XOXODAY_DEFAULT_COUNTRY', 'IN'),

    'order_email'   => env('XOXODAY_ORDER_EMAIL', 'orders@avirqo.in'),
    'order_contact' => env('XOXODAY_ORDER_CONTACT', '+91-0000000000'),

    'timeout'       => (int) env('XOXODAY_TIMEOUT', 30),
];
