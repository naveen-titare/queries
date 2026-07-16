<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

require __DIR__.'/modules/auth.php';
require __DIR__.'/modules/customers.php';
require __DIR__.'/modules/vouchers.php';
require __DIR__.'/modules/send-vouchers.php';
