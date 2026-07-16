<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Vouchers\VoucherController;
use App\Http\Controllers\Api\Vouchers\XoxodayCatalogController;

/*
| Vouchers routes — matches the project convention (routes/modules/customers.php):
|   Route::middleware('auth:sanctum')->prefix('customers')->group(...)
| Resolves to /api/vouchers/... (Laravel adds the /api prefix).
*/
Route::middleware('auth:sanctum')->prefix('vouchers')->group(function () {

    // Live Xoxoday catalog (for the "Fetch from Xoxoday" flow)
    Route::get('xoxoday/filters', [XoxodayCatalogController::class, 'filters']);
    Route::get('xoxoday/catalog', [XoxodayCatalogController::class, 'vouchers']);
    Route::get('xoxoday/balance', [XoxodayCatalogController::class, 'balance']);

    // Import (fetch) selected vouchers into Avirqo
    Route::post('import', [VoucherController::class, 'import']);

    // Avirqo inventory + import history
    Route::get('inventory', [VoucherController::class, 'inventory']);
    Route::get('history',   [VoucherController::class, 'history']);
});
