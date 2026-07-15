<?php

use App\Http\Controllers\Api\Customers\CustomerController;
use App\Http\Controllers\Api\Customers\CustomerDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('customers')->group(function () {
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
});
