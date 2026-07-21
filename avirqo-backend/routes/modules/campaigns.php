<?php
use App\Http\Controllers\Api\Campaigns\VoucherCampaignController;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth:sanctum', 'module.access:campaigns'])->prefix('voucher-campaigns')->group(function () {
 Route::get('/', [VoucherCampaignController::class,'index']); Route::post('/', [VoucherCampaignController::class,'store']); Route::put('/{campaign}', [VoucherCampaignController::class,'update']);
 Route::get('/{campaign}/products', [VoucherCampaignController::class,'products']); Route::put('/{campaign}/products', [VoucherCampaignController::class,'saveProducts']);
 Route::get('/{campaign}/customers', [VoucherCampaignController::class,'customers']); Route::put('/{campaign}/customers', [VoucherCampaignController::class,'saveCustomers']);
 Route::post('/{campaign}/otp/verify', [VoucherCampaignController::class,'verifyCampaignOtp']); Route::post('/{campaign}/otp/resend', [VoucherCampaignController::class,'resendCampaignOtp']);
});
Route::middleware(['auth:sanctum', 'module.access:campaigns'])->get('customers/{customer}/voucher-campaign', [VoucherCampaignController::class,'customerCampaign']);
