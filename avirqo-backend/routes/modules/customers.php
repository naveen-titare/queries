<?php

use App\Http\Controllers\Api\Customers\CustomerController;
use App\Http\Controllers\Api\Customers\CustomerDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access:customers'])->prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    Route::patch('/{customer}/status', [CustomerController::class, 'setStatus']);
    Route::post('/{customer}/balance', [CustomerController::class, 'adjustBalance']);

    // Documents
    Route::post('/{customer}/documents', [CustomerDocumentController::class, 'upload']);
    Route::get('/{customer}/documents/{document}/download', [CustomerDocumentController::class, 'download']);
    Route::delete('/{customer}/documents/{document}', [CustomerDocumentController::class, 'destroy']);

    // SPOCs - Active only (for order cart/catalog)
    Route::get('/{customer}/spocs/active', [CustomerController::class, 'getActiveSpocs']);
    
    // SPOC Status toggle with OTP verification
    Route::post('/{customer}/spocs/{spoc}/status', [CustomerController::class, 'toggleSpocStatus']);
    Route::post('/{customer}/spocs/{spoc}/otp/initiate', [CustomerController::class, 'initiateSpocOtp']);
    Route::post('/{customer}/spocs/{spoc}/otp/verify', [CustomerController::class, 'verifySpocOtp']);
});
