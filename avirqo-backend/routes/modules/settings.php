<?php

use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access:settings'])->prefix('settings')->group(function () {
    Route::get('/profile', [SettingsController::class, 'profile']);
    Route::match(['put', 'post'], '/profile', [SettingsController::class, 'updateProfile']);
    Route::post('/profile/password', [SettingsController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum', 'module.access:manager_module_access'])->prefix('settings')->group(function () {
    Route::get('/users', [SettingsController::class, 'index']);
    Route::post('/users', [SettingsController::class, 'store']);
    Route::put('/users/{user}', [SettingsController::class, 'update']);
    Route::post('/users/{user}/reset-password', [SettingsController::class, 'resetPassword']);
});
