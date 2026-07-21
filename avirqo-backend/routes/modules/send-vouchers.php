<?php

use App\Http\Controllers\Api\SendVouchers\SendVoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access:send_vouchers|voucher_inventory'])->prefix('send-vouchers')->group(function () {
    // Catalog - renamed to avoid conflict with existing 'vouchers' module
    Route::get('/catalog', [SendVoucherController::class, 'catalog']);
    Route::get('/catalog/{id}', [SendVoucherController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'module.access:send_vouchers'])->prefix('send-vouchers')->group(function () {
    Route::get('/customers', [SendVoucherController::class, 'customers']);
    Route::get('/customers/{customer}', [SendVoucherController::class, 'customer']);
    Route::get('/customers/{customer}/voucher-campaign', [SendVoucherController::class, 'customerCampaign']);
    Route::get('/global-margins', [SendVoucherController::class, 'globalMargins']);
    Route::put('/global-margins', [SendVoucherController::class, 'saveGlobalMargins']);
    Route::post('/global-margins/verify', [SendVoucherController::class, 'verifyGlobalMarginsOtp']);
    Route::post('/global-margins/resend-otp', [SendVoucherController::class, 'resendGlobalMarginsOtp']);
    Route::post('/cart/validate', [SendVoucherController::class, 'validateCart']);

    // Order placement - NEW OTP-based flow (2-step)
    Route::post('/orders/initiate', [SendVoucherController::class, 'initiateOrder']);      // Step 1: Initiate order, send OTP
    Route::post('/orders', [SendVoucherController::class, 'placeOrder']); // Legacy direct order placement (without OTP)
});

Route::middleware(['auth:sanctum', 'module.access:send_vouchers|order_history'])->prefix('send-vouchers')->group(function () {
    Route::post('/orders/{id}/verify-otp', [SendVoucherController::class, 'verifyOrderOtp']); // Step 2: Verify OTP, send vouchers
    Route::post('/orders/{id}/resend-otp', [SendVoucherController::class, 'resendOrderOtp']); // Resend OTP
    Route::post('/orders/{id}/cancel', [SendVoucherController::class, 'cancelOrder']); // Cancel order & restore balance
    Route::post('/orders/{orderNumber}/switch-spoc/initiate', [SendVoucherController::class, 'initiateOrderSpocSwitch']);
    Route::post('/orders/{orderNumber}/switch-spoc/verify', [SendVoucherController::class, 'verifyOrderSpocSwitch']);
    Route::get('/orders', [SendVoucherController::class, 'orderHistory']);
    Route::get('/orders/{id}', [SendVoucherController::class, 'orderDetail']);
    Route::post('/orders/{id}/retry', [SendVoucherController::class, 'retryOrder']);
    Route::post('/orders/{id}/verify', [SendVoucherController::class, 'verifyOrder']);
    Route::post('/orders/{id}/resend-email', [SendVoucherController::class, 'resendEmail']);
});
