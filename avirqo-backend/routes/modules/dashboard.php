<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access:dashboard'])->prefix('dashboard')->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary']);
});
