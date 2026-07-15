<?php

use App\Http\Controllers\Api\Vouchers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('vouchers')->group(function () {
    Route::get('/catalog', [VoucherController::class, 'catalog']);
    Route::get('/catalog/{id}', [VoucherController::class, 'show']);
    Route::post('/cart/validate', [VoucherController::class, 'validateCart']);
    Route::post('/orders', [VoucherController::class, 'placeOrder']);
    Route::get('/orders', [VoucherController::class, 'orderHistory']);
    Route::get('/orders/{id}', [VoucherController::class, 'orderDetail']);
});
