<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SendVouchers\PublicCodeVerificationController;
use App\Http\Controllers\Api\SendVouchers\PublicVoucherDeliveryController;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Voucher Code Verification - Public endpoint (Send Voucher module)
Route::get('public/send-vouchers/codes/verify/{id}', [PublicCodeVerificationController::class, 'verify']);

// Public delivery page flow for downloading vouchers in Excel
Route::get('public/send-vouchers/orders/{orderNumber}/delivery', [PublicVoucherDeliveryController::class, 'show']);
Route::post('public/send-vouchers/orders/{orderNumber}/delivery/request-otp', [PublicVoucherDeliveryController::class, 'requestOtp']);
Route::post('public/send-vouchers/orders/{orderNumber}/delivery/verify-otp', [PublicVoucherDeliveryController::class, 'verifyOtp']);
Route::post('public/send-vouchers/orders/{orderNumber}/delivery/download', [PublicVoucherDeliveryController::class, 'download']);
