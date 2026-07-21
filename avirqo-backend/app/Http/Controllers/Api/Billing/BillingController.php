<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingCreditDebitNote;
use App\Models\BillingOtpApprover;
use App\Models\BillingPayment;
use App\Models\Customer;
use App\Models\ProformaInvoice;
use App\Models\SendVoucherCode;
use App\Models\SendVoucherProduct;
use App\Models\TaxInvoice;
use App\Models\VoucherCampaign;
use App\Models\VoucherCampaignProduct;
use App\Mail\BillingControlOtpMail;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    public function customers(Request $request)
    {
        $customers = Customer::query()
            ->where('status', 'active')
            ->when($request->query('search'), fn ($q, $search) => $q->where('company_name', 'like', "%{$search}%"))
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'location', 'gst_number', 'balance', 'status']);

        return response()->json(['data' => $customers]);
    }

    public function products(Request $request)
    {
        $products = SendVoucherProduct::query()
            ->where('is_active', true)
            ->where('is_blacklisted', false)
            ->when($request->query('search'), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('brand', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('brand')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'brand',
                'currency_code',
                'value_denominations',
                'min_value',
                'max_value',
                'is_active',
            ]);

        $denominations = SendVoucherCode::query()
            ->select('product_id', 'denomination')
            ->whereIn('product_id', $products->pluck('id'))
            ->distinct()
            ->orderBy('denomination')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('denomination')->map(fn ($value) => (float) $value)->values());

        $products->transform(function ($product) use ($denominations) {
            $product->denominations = $denominations->get($product->id, collect())->values();
            return $product;
        });

        return response()->json(['data' => $products]);
    }

    public function customerCampaignDiscounts(Customer $customer)
    {
        $campaign = $customer->belongsToMany(VoucherCampaign::class, 'voucher_campaign_customers', 'customer_id', 'campaign_id')
            ->where('is_active', true)
            ->first();

        if (! $campaign) {
            return response()->json([
                'campaign' => null,
                'discounts' => [],
            ]);
        }

        $discounts = VoucherCampaignProduct::where('campaign_id', $campaign->id)
            ->where('is_blacklisted', false)
            ->pluck('discount_percentage', 'product_id')
            ->map(fn ($value) => (float) $value);
        $blacklistedProductIds = VoucherCampaignProduct::where('campaign_id', $campaign->id)
            ->where('is_blacklisted', true)
            ->pluck('product_id')
            ->map(fn ($value) => (int) $value)
            ->values();

        return response()->json([
            'campaign' => $campaign,
            'discounts' => $discounts,
            'blacklisted_product_ids' => $blacklistedProductIds,
        ]);
    }

    public function proformas(Request $request)
    {
        return response()->json($this->billing->listProformas($request->only('customer_id', 'status', 'search')));
    }

    public function storeProforma(Request $request)
    {
        $data = $this->validateProforma($request);
        return response()->json([
            'message' => 'Proforma Invoice draft created.',
            'data' => $this->billing->storeProforma($data, (int) $request->user()->id),
        ], 201);
    }

    public function showProforma(ProformaInvoice $proformaInvoice)
    {
        return response()->json($proformaInvoice->load(['customer', 'items.product', 'payments', 'creditNoteApplications.creditNote']));
    }

    public function updateProforma(Request $request, ProformaInvoice $proformaInvoice)
    {
        $data = $this->validateProforma($request, true);
        return response()->json([
            'message' => 'Proforma Invoice updated.',
            'data' => $this->billing->updateProforma($proformaInvoice, $data, (int) $request->user()->id),
        ]);
    }

    public function finalizeProforma(Request $request, ProformaInvoice $proformaInvoice)
    {
        return response()->json([
            'message' => 'Proforma Invoice finalized.',
            'data' => $this->billing->finalizeProforma($proformaInvoice, (int) $request->user()->id),
        ]);
    }

    public function cancelProforma(Request $request, ProformaInvoice $proformaInvoice)
    {
        return response()->json([
            'message' => 'OTP verification is required to cancel a Proforma Invoice.',
            'requires_otp' => true,
        ], 422);
    }

    public function requestCancelProformaOtp(Request $request, ProformaInvoice $proformaInvoice)
    {
        if ((float) $proformaInvoice->delivered_amount > 0) {
            return response()->json(['message' => 'PI has delivered vouchers. Update/reconcile the PI instead of cancelling it.'], 422);
        }

        if ($proformaInvoice->status === 'cancelled') {
            return response()->json(['message' => 'This Proforma Invoice is already cancelled.'], 422);
        }

        $requestId = (string) Str::uuid();
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $recipients = $this->billing->approverEmails('billing_control');

        Cache::put($this->cancelPiOtpCacheKey($proformaInvoice->id, (int) $request->user()->id, $requestId), [
            'pi_id' => $proformaInvoice->id,
            'user_id' => (int) $request->user()->id,
            'otp_hash' => Hash::make($otp),
            'recipients' => $recipients,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        Mail::to($recipients)->send(new BillingControlOtpMail(
            actionLabel: 'Proforma Invoice Cancellation',
            otp: $otp,
            documentNumber: $proformaInvoice->pi_number ?: $proformaInvoice->draft_number,
            customerName: $proformaInvoice->customer?->company_name ?? '—',
            totalAmount: (float) $proformaInvoice->total_amount,
            requestedBy: $request->user()->name ?? $request->user()->email ?? 'Avirqo user',
            expiresAt: $expiresAt,
            piPdfContent: $this->billing->renderDocumentPdf('proforma_invoice', $proformaInvoice->id),
        ));

        return response()->json([
            'message' => 'OTP sent to billing control approvers. Please verify within 10 minutes to cancel PI.',
            'requires_otp' => true,
            'request_id' => $requestId,
            'recipients' => $recipients,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function resendCancelProformaOtp(Request $request, ProformaInvoice $proformaInvoice)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
        ]);

        $key = $this->cancelPiOtpCacheKey($proformaInvoice->id, (int) $request->user()->id, $data['request_id']);
        $pending = Cache::get($key);
        if (! $pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please initiate cancellation again.'], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $recipients = $pending['recipients'] ?? $this->billing->approverEmails('billing_control');
        $pending['otp_hash'] = Hash::make($otp);
        $pending['expires_at'] = $expiresAt->toIso8601String();
        $pending['recipients'] = $recipients;

        Cache::put($key, $pending, $expiresAt);

        Mail::to($recipients)->send(new BillingControlOtpMail(
            actionLabel: 'Proforma Invoice Cancellation',
            otp: $otp,
            documentNumber: $proformaInvoice->pi_number ?: $proformaInvoice->draft_number,
            customerName: $proformaInvoice->customer?->company_name ?? '—',
            totalAmount: (float) $proformaInvoice->total_amount,
            requestedBy: $request->user()->name ?? $request->user()->email ?? 'Avirqo user',
            expiresAt: $expiresAt,
            piPdfContent: $this->billing->renderDocumentPdf('proforma_invoice', $proformaInvoice->id),
        ));

        return response()->json([
            'message' => 'OTP resent successfully. Please verify within 10 minutes.',
            'request_id' => $data['request_id'],
            'recipients' => $recipients,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function verifyCancelProformaOtp(Request $request, ProformaInvoice $proformaInvoice)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $key = $this->cancelPiOtpCacheKey($proformaInvoice->id, (int) $request->user()->id, $data['request_id']);
        $pending = Cache::get($key);
        if (! $pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please initiate cancellation again.'], 422);
        }

        if (! Hash::check($data['otp'], $pending['otp_hash'] ?? '')) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        Cache::forget($key);

        return response()->json([
            'message' => 'Proforma Invoice cancelled.',
            'data' => $this->billing->cancelProforma($proformaInvoice, (int) $request->user()->id),
        ]);
    }

    public function paidProformasForCustomer(int $customerId)
    {
        return response()->json(['data' => $this->billing->paidProformasForCustomer($customerId)]);
    }

    public function payments(Request $request)
    {
        $payments = BillingPayment::with(['customer', 'proformaInvoice'])
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('payment_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('company_name', 'like', "%{$s}%"))
                    ->orWhereHas('proformaInvoice', fn ($pq) => $pq
                        ->where('pi_number', 'like', "%{$s}%")
                        ->orWhere('draft_number', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function capturePayment(Request $request)
    {
        $data = $request->validate([
            'proforma_invoice_id' => ['required', 'integer', 'exists:proforma_invoices,id'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'details' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ]);

        return response()->json([
            'message' => 'Payment captured and customer balance updated.',
            'data' => $this->billing->capturePayment($data, (int) $request->user()->id, $request->file('attachment')),
        ], 201);
    }

    public function invalidatePayment(Request $request, BillingPayment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        return response()->json([
            'message' => 'Payment marked invalid and balance reversed.',
            'data' => $this->billing->invalidatePayment($payment, $data['reason'], (int) $request->user()->id),
        ]);
    }

    public function taxInvoices(Request $request)
    {
        $invoices = TaxInvoice::with(['customer', 'proformaInvoice', 'items'])
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('invoice_number', 'like', "%{$s}%")
                    ->orWhere('draft_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('company_name', 'like', "%{$s}%"))
                    ->orWhereHas('proformaInvoice', fn ($pq) => $pq
                        ->where('pi_number', 'like', "%{$s}%")
                        ->orWhere('draft_number', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20);

        return response()->json($invoices);
    }

    public function showTaxInvoice(TaxInvoice $taxInvoice)
    {
        return response()->json($taxInvoice->load(['customer', 'proformaInvoice', 'items.product', 'order']));
    }

    public function notes(Request $request)
    {
        $notes = BillingCreditDebitNote::with(['customer', 'proformaInvoice', 'payment', 'applications.proformaInvoice'])
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('note_number', 'like', "%{$s}%")
                    ->orWhere('draft_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('company_name', 'like', "%{$s}%"))
                    ->orWhereHas('proformaInvoice', fn ($pq) => $pq
                        ->where('pi_number', 'like', "%{$s}%")
                        ->orWhere('draft_number', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20);

        return response()->json($notes);
    }

    public function customerCreditNotes(Request $request, Customer $customer)
    {
        return response()->json([
            'data' => $this->billing->availableCreditNotesForCustomer($customer->id, $request->integer('proforma_invoice_id') ?: null),
        ]);
    }

    public function applyCreditNoteToPiBalance(Request $request, BillingCreditDebitNote $note)
    {
        $data = $request->validate([
            'proforma_invoice_id' => ['required', 'integer', 'exists:proforma_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        return response()->json([
            'message' => 'Credit note applied to PI balance.',
            'data' => $this->billing->applyCreditNoteToPiBalance(
                $note,
                ProformaInvoice::findOrFail($data['proforma_invoice_id']),
                (float) $data['amount'],
                (int) $request->user()->id
            ),
        ]);
    }

    public function pendingProformasForCreditNote(BillingCreditDebitNote $note)
    {
        return response()->json([
            'data' => $this->billing->pendingProformasForCreditNote($note),
        ]);
    }

    public function approvers()
    {
        $defaults = collect([
            ['group_key' => 'global_margin', 'label' => 'Global Margin'],
            ['group_key' => 'campaign_changes', 'label' => 'Campaign Changes'],
            ['group_key' => 'order_otp', 'label' => 'Order OTP'],
            ['group_key' => 'billing_control', 'label' => 'Billing Control'],
        ]);

        $existing = BillingOtpApprover::all()->keyBy('group_key');
        return response()->json([
            'data' => $defaults->map(fn ($row) => $existing->get($row['group_key']) ?: [
                'group_key' => $row['group_key'],
                'label' => $row['label'],
                'emails' => ['naveentitare52@gmail.com', 'ptitare@gmail.com'],
                'is_active' => true,
            ])->values(),
        ]);
    }

    public function saveApprovers(Request $request)
    {
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*.group_key' => ['required', 'string', 'max:80'],
            'groups.*.label' => ['required', 'string', 'max:120'],
            'groups.*.emails' => ['required', 'array', 'min:1'],
            'groups.*.emails.*' => ['required', 'email'],
            'groups.*.is_active' => ['required', 'boolean'],
        ]);

        foreach ($data['groups'] as $group) {
            BillingOtpApprover::updateOrCreate(
                ['group_key' => $group['group_key']],
                [
                    'label' => $group['label'],
                    'emails' => array_values(array_unique($group['emails'])),
                    'is_active' => (bool) $group['is_active'],
                ]
            );
        }

        return response()->json(['message' => 'OTP approvers saved.']);
    }

    public function downloadDocument(string $type, int $id)
    {
        $pdf = $this->billing->renderDocumentPdf($type, $id);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$type.'-'.$id.'.pdf"',
        ]);
    }

    public function emailInternalDocument(Request $request, string $type, int $id)
    {
        $data = $request->validate([
            'to_email' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->billing->emailInternalDocument($type, $id, $data['to_email'], $data['message'] ?? null, (int) $request->user()->id);
        return response()->json(['message' => 'Document emailed internally.']);
    }

    private function validateProforma(Request $request, bool $isUpdate = false): array
    {
        $minIssueDate = now()->subDays(5)->toDateString();
        $maxIssueDate = now()->toDateString();

        return $request->validate([
            'customer_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:customers,id'],
            'discount_type' => ['nullable', 'in:campaign,invoice'],
            'invoice_discount_percentage' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'issue_date' => $isUpdate
                ? ['prohibited']
                : ['nullable', 'date', 'after_or_equal:' . $minIssueDate, 'before_or_equal:' . $maxIssueDate],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:send_voucher_products,id'],
            'items.*.denomination' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:0'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:50'],
            'credit_note_applications' => ['nullable', 'array'],
            'credit_note_applications.*.credit_note_id' => ['required_with:credit_note_applications', 'integer', 'exists:billing_credit_debit_notes,id'],
            'credit_note_applications.*.amount' => ['required_with:credit_note_applications', 'numeric', 'min:0.01'],
        ]);
    }

    private function cancelPiOtpCacheKey(int $piId, int $userId, string $requestId): string
    {
        return "billing:cancel-pi-otp:{$piId}:{$userId}:{$requestId}";
    }
}
