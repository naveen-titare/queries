<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Auth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/verify-2fa', [TwoFactorController::class, 'verifyLogin']);
    Route::post('/reauth/request', [TwoFactorController::class, 'reauthRequest']);
    Route::post('/reauth/verify', [TwoFactorController::class, 'reauthVerify']);

    // First-time enrollment and lost-device reset
    Route::post('/2fa/setup/confirm', [TwoFactorSetupController::class, 'confirm']);
    Route::post('/2fa/reset/request', [TwoFactorSetupController::class, 'requestReset']);
    Route::post('/2fa/reset/{token}/verify', [TwoFactorSetupController::class, 'verifyResetToken']);

    Route::middleware('auth:sanctum')->post('/logout', [LogoutController::class, 'logout']);
});
