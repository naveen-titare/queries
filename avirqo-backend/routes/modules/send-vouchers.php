<?php

use App\Http\Controllers\Api\SendVouchers\SendVoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('send-vouchers')->group(function () {
    // Catalog - renamed to avoid conflict with existing 'vouchers' module
    Route::get('/catalog', [SendVoucherController::class, 'catalog']);
    Route::get('/catalog/{id}', [SendVoucherController::class, 'show']);
    Route::post('/cart/validate', [SendVoucherController::class, 'validateCart']);
    
    // Order placement - NEW OTP-based flow (2-step)
    Route::post('/orders/initiate', [SendVoucherController::class, 'initiateOrder']);      // Step 1: Initiate order, send OTP
    Route::post('/orders/{id}/verify-otp', [SendVoucherController::class, 'verifyOrderOtp']); // Step 2: Verify OTP, send vouchers
    Route::post('/orders/{id}/resend-otp', [SendVoucherController::class, 'resendOrderOtp']); // Resend OTP
    Route::post('/orders/{id}/cancel', [SendVoucherController::class, 'cancelOrder']); // Cancel order & restore balance
    
    // Legacy direct order placement (without OTP) - kept for backward compatibility
    Route::post('/orders', [SendVoucherController::class, 'placeOrder']);
    
    Route::get('/orders', [SendVoucherController::class, 'orderHistory']);
    Route::get('/orders/{id}', [SendVoucherController::class, 'orderDetail']);
    Route::post('/orders/{id}/retry', [SendVoucherController::class, 'retryOrder']);
    
    // Verification endpoint - verify codes in Excel match database
    Route::post('/orders/{id}/verify', [SendVoucherController::class, 'verifyOrder']);
    Route::post('/orders/{id}/resend-email', [SendVoucherController::class, 'resendEmail']);
});