<?php

use App\Http\Controllers\Api\Billing\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access:send_vouchers|billing'])->prefix('billing')->group(function () {
    Route::get('/proforma-invoices/paid/customer/{customerId}', [BillingController::class, 'paidProformasForCustomer']);
});

Route::middleware(['auth:sanctum', 'module.access:billing'])->prefix('billing')->group(function () {
    Route::get('/customers', [BillingController::class, 'customers']);
    Route::get('/customers/{customer}/campaign-discounts', [BillingController::class, 'customerCampaignDiscounts']);
    Route::get('/customers/{customer}/credit-notes', [BillingController::class, 'customerCreditNotes']);
    Route::get('/products', [BillingController::class, 'products']);

    Route::get('/proforma-invoices', [BillingController::class, 'proformas']);
    Route::post('/proforma-invoices', [BillingController::class, 'storeProforma']);
    Route::get('/proforma-invoices/{proformaInvoice}', [BillingController::class, 'showProforma']);
    Route::put('/proforma-invoices/{proformaInvoice}', [BillingController::class, 'updateProforma']);
    Route::post('/proforma-invoices/{proformaInvoice}/finalize', [BillingController::class, 'finalizeProforma']);
    Route::post('/proforma-invoices/{proformaInvoice}/cancel', [BillingController::class, 'cancelProforma']);
    Route::post('/proforma-invoices/{proformaInvoice}/cancel-otp', [BillingController::class, 'requestCancelProformaOtp']);
    Route::post('/proforma-invoices/{proformaInvoice}/cancel-otp/resend', [BillingController::class, 'resendCancelProformaOtp']);
    Route::post('/proforma-invoices/{proformaInvoice}/cancel-otp/verify', [BillingController::class, 'verifyCancelProformaOtp']);

    Route::get('/payments', [BillingController::class, 'payments']);
    Route::post('/payments', [BillingController::class, 'capturePayment']);
    Route::post('/payments/{payment}/invalidate', [BillingController::class, 'invalidatePayment']);

    Route::get('/tax-invoices', [BillingController::class, 'taxInvoices']);
    Route::get('/tax-invoices/{taxInvoice}', [BillingController::class, 'showTaxInvoice']);

    Route::get('/credit-debit-notes', [BillingController::class, 'notes']);
    Route::get('/credit-debit-notes/{note}/pending-proformas', [BillingController::class, 'pendingProformasForCreditNote']);
    Route::post('/credit-debit-notes/{note}/apply-to-pi-balance', [BillingController::class, 'applyCreditNoteToPiBalance']);
    Route::get('/reports', fn () => response()->json(['message' => 'Reports coming soon.']));

    Route::get('/otp-approvers', [BillingController::class, 'approvers']);
    Route::put('/otp-approvers', [BillingController::class, 'saveApprovers']);

    Route::get('/documents/{type}/{id}/download', [BillingController::class, 'downloadDocument']);
    Route::post('/documents/{type}/{id}/email-internal', [BillingController::class, 'emailInternalDocument']);
});
