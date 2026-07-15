<?php

use App\Http\Controllers\Api\SendVouchers\SendVoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('send-vouchers')->group(function () {
    // Catalog - renamed to avoid conflict with existing 'vouchers' module
    Route::get('/catalog', [SendVoucherController::class, 'catalog']);
    Route::get('/catalog/{id}', [SendVoucherController::class, 'show']);
    Route::post('/cart/validate', [SendVoucherController::class, 'validateCart']);
    Route::post('/orders', [SendVoucherController::class, 'placeOrder']);
    Route::get('/orders', [SendVoucherController::class, 'orderHistory']);
    Route::get('/orders/{id}', [SendVoucherController::class, 'orderDetail']);
    Route::post('/orders/{id}/retry', [SendVoucherController::class, 'retryOrder']);
});
