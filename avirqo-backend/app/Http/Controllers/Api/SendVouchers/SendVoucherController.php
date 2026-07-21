<?php

namespace App\Http\Controllers\Api\SendVouchers;

use App\Mail\GlobalMarginOtpMail;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SendVoucherOrder;
use App\Models\SendVoucherProduct;
use App\Models\BillingOtpApprover;
use App\Models\VoucherCampaign;
use App\Services\SendVouchers\SendVoucherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SendVoucherController extends Controller
{
    public function __construct(protected SendVoucherService $service) {}

    public function catalog(Request $request)
    {
        return response()->json(
            $this->service->catalog($request->only('search', 'usage_type', 'country_code', 'customer_id'))
        );
    }

    public function show(int $id)
    {
        return response()->json($this->service->getProduct($id));
    }

    public function customers(Request $request)
    {
        $customers = Customer::with(['spocs', 'voucherCampaigns' => fn ($query) => $query->where('is_active', true)])
            ->where('status', 'active')
            ->whereHas('voucherCampaigns', fn ($query) => $query->where('is_active', true))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('company_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('gst_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'location', 'gst_number', 'registration_number', 'balance', 'status']);

        return response()->json(['data' => $customers]);
    }

    public function customer(Customer $customer)
    {
        abort_if($customer->status !== 'active', 404);

        return response()->json(
            $customer->load(['spocs', 'voucherCampaigns' => fn ($query) => $query->where('is_active', true)])
        );
    }

    public function customerCampaign(Customer $customer)
    {
        abort_if($customer->status !== 'active', 404);

        return response()->json(
            $customer
                ->belongsToMany(VoucherCampaign::class, 'voucher_campaign_customers', 'customer_id', 'campaign_id')
                ->where('is_active', true)
                ->first()
        );
    }

    public function globalMargins()
    {
        $columns = ['id', 'name', 'brand', 'global_margin_percentage'];
        if (Schema::hasColumn('send_voucher_products', 'is_blacklisted')) {
            $columns[] = 'is_blacklisted';
        }

        return response()->json(
            SendVoucherProduct::where('is_active', true)
                ->orderBy('brand')
                ->orderBy('name')
                ->get($columns)
                ->map(function (SendVoucherProduct $product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'brand' => $product->brand,
                        'global_margin_percentage' => (float) $product->global_margin_percentage,
                        'is_blacklisted' => (bool) ($product->is_blacklisted ?? false),
                    ];
                })
        );
    }

    public function saveGlobalMargins(Request $request)
    {
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:send_voucher_products,id'],
            'products.*.global_margin_percentage' => ['required', 'numeric', 'min:-100', 'max:100'],
            'products.*.is_blacklisted' => ['required', 'boolean'],
        ]);

        $requestId = (string) Str::uuid();
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $recipients = $this->approverEmails('global_margin');
        $requestedBy = $request->user()->name ?: $request->user()->email;
        $currentProducts = SendVoucherProduct::where('is_active', true)
            ->orderBy('brand')
            ->orderBy('name')
            ->get($this->globalMarginColumns());
        $requestedMargins = collect($data['products'])->keyBy('id');

        $catalogRows = $currentProducts->map(function (SendVoucherProduct $product) use ($requestedMargins) {
            $requested = $requestedMargins[$product->id] ?? null;
            $newValue = (float) ($requested['global_margin_percentage'] ?? $product->global_margin_percentage);
            $newBlacklist = (bool) ($requested['is_blacklisted'] ?? $product->is_blacklisted);
            return [
                'id' => $product->id,
                'brand' => $product->brand,
                'name' => $product->name,
                'old_margin_percentage' => (float) $product->global_margin_percentage,
                'new_margin_percentage' => $newValue,
                'old_is_blacklisted' => (bool) $product->is_blacklisted,
                'new_is_blacklisted' => $newBlacklist,
            ];
        })->values()->all();

        $changes = array_values(array_filter($catalogRows, fn (array $row) =>
            (float) $row['old_margin_percentage'] !== (float) $row['new_margin_percentage']
            || (bool) $row['old_is_blacklisted'] !== (bool) $row['new_is_blacklisted']
        ));

        $payload = [
            'request_id' => $requestId,
            'user_id' => (int) $request->user()->id,
            'requested_by' => $requestedBy,
            'otp' => $otp,
            'changes' => $changes,
            'products' => $catalogRows,
            'requested_products' => collect($data['products'])->map(function (array $product) {
                return [
                    'id' => (int) $product['id'],
                    'global_margin_percentage' => (float) $product['global_margin_percentage'],
                    'is_blacklisted' => (bool) ($product['is_blacklisted'] ?? false),
                ];
            })->values()->all(),
            'recipients' => $recipients,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($this->globalMarginOtpCacheKey($request->user()->id, $requestId), $payload, $expiresAt);

        try {
            $this->sendGlobalMarginOtpMail($recipients, $otp, $changes, $catalogRows, $requestedBy, $expiresAt);
        } catch (\Throwable $e) {
            Cache::forget($this->globalMarginOtpCacheKey($request->user()->id, $requestId));
            throw $e;
        }

        return response()->json([
            'message' => 'OTP sent to approved admin emails. Please verify within 10 minutes to save global margins.',
            'requires_otp' => true,
            'request_id' => $requestId,
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $recipients,
        ]);
    }

    public function verifyGlobalMarginsOtp(Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $cacheKey = $this->globalMarginOtpCacheKey($request->user()->id, $data['request_id']);
        $pending = Cache::get($cacheKey);

        if (!$pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please save the margins again.'], 422);
        }

        if (($pending['otp'] ?? null) !== $data['otp']) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        $expiresAt = data_get($pending, 'expires_at');
        if ($expiresAt && now()->greaterThan(Carbon::parse($expiresAt))) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'OTP expired. Please save the margins again.'], 422);
        }

        foreach ($pending['requested_products'] as $product) {
            $update = ['global_margin_percentage' => $product['global_margin_percentage']];
            if (Schema::hasColumn('send_voucher_products', 'is_blacklisted')) {
                $update['is_blacklisted'] = $product['is_blacklisted'];
            }
            SendVoucherProduct::whereKey($product['id'])->update($update);
        }

        Cache::forget($cacheKey);

        return response()->json(['message' => 'Global margins saved successfully.']);
    }

    public function resendGlobalMarginsOtp(Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
        ]);

        $cacheKey = $this->globalMarginOtpCacheKey($request->user()->id, $data['request_id']);
        $pending = Cache::get($cacheKey);

        if (!$pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please save the margins again.'], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $pending['otp'] = $otp;
        $pending['expires_at'] = $expiresAt->toIso8601String();
        Cache::put($cacheKey, $pending, $expiresAt);

        try {
            $this->sendGlobalMarginOtpMail(
                $pending['recipients'] ?? $this->approverEmails('global_margin'),
                $otp,
                $pending['changes'] ?? [],
                $pending['products'] ?? [],
                $pending['requested_by'] ?? ($request->user()->name ?: $request->user()->email),
                $expiresAt
            );
        } catch (\Throwable $e) {
            throw $e;
        }

        return response()->json([
            'message' => 'OTP resent successfully. Please verify within 10 minutes.',
            'request_id' => $data['request_id'],
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $pending['recipients'] ?? [],
        ]);
    }

    public function validateCart(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'proforma_invoice_id' => ['nullable', 'integer', 'exists:proforma_invoices,id'],
            'pricing_mode' => ['sometimes', 'in:product,invoice'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:send_voucher_products,id'],
            'items.*.denomination' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->service->validateCart($data['items'], $data['customer_id'], $data['pricing_mode'] ?? 'product', $data['proforma_invoice_id'] ?? null);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cart validation failed.', 'errors' => [$e->getMessage()]], 422);
        }

        if (!empty($result['errors'])) {
            return response()->json(['message' => 'Cart validation failed.', 'errors' => $result['errors']], 422);
        }

        return response()->json($result);
    }

    /**
     * Step 1: Initiate order - validates, reserves codes, deducts balance, sends OTP
     * POST /api/send-vouchers/orders/initiate
     */
    public function initiateOrder(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'spoc_id' => ['required', 'integer', 'exists:customer_spocs,id'],
            'proforma_invoice_id' => ['required', 'integer', 'exists:proforma_invoices,id'],
            'pricing_mode' => ['required', 'in:product,invoice'],
            'invoice_discount_percentage' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:send_voucher_products,id'],
            'items.*.denomination' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->service->initiateOrder($data, $request->user()->id);
            return response()->json([
                'message' => 'Order initiated. OTP sent to SPOC and admin emails.',
                'order' => $order,
                'requires_otp' => true,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Send Voucher Order Initiate Failed: ' . $e->getMessage(), ['data' => $data]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Step 2: Verify OTP and complete order
     * POST /api/send-vouchers/orders/{id}/verify-otp
     * Body: { "otp": "123456" }
     */
    public function verifyOrderOtp(Request $request, string $id)
{
    $data = $request->validate([
        'otp' => ['required', 'string', 'size:6'],
    ]);

        try {
            $order = $this->service->verifyOrderOtp($id, $data['otp'], $request->user()->id);
            return response()->json([
                'message' => 'OTP verified. Delivery secret email sent successfully.',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
}


    /**
     * Resend OTP for an order
     * POST /api/send-vouchers/orders/{id}/resend-otp
     */
    public function resendOrderOtp(string $id)
{
    try {
        $order = $this->service->resendOrderOtp($id);
        return response()->json([
            'message' => 'OTP resent successfully.',
            'order' => $order,
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

    /** 
     * Cancel order and restore balance
     * POST /api/send-vouchers/orders/{order_number}/cancel
     */
    public function cancelOrder(string $order_number)
    {
        try {
            $order = $this->service->cancelOrder($order_number);
            return response()->json([
                'message' => 'Order cancelled. Balance restored.',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // Legacy direct order placement (without OTP) - kept for backward compatibility
    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'spoc_id' => ['required', 'integer', 'exists:customer_spocs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:send_voucher_products,id'],
            'items.*.denomination' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->service->processOrder($data, $request->user()->id);
            return response()->json([
                'message' => 'Vouchers sent successfully.',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Send Voucher Order Failed: ' . $e->getMessage(), ['data' => $data]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function orderHistory(Request $request)
    {
        return response()->json(
            $this->service->orderHistory($request->only('customer_id', 'status', 'search', 'date_from', 'date_to'))
        );
    }

    public function orderDetail(string $id)
    {
        $order = SendVoucherOrder::with(['customer.spocs', 'spoc', 'sentBy', 'items.product', 'items.codes'])
            ->where(function ($query) use ($id) {
                $query->whereKey($id)
                    ->orWhere('order_number', $id);
            })
            ->firstOrFail();
        return response()->json($order);
    }

    public function initiateOrderSpocSwitch(Request $request, string $orderNumber)
    {
        $data = $request->validate([
            'spoc_id' => ['required', 'integer', 'exists:customer_spocs,id'],
        ]);

        try {
            return response()->json(
                $this->service->initiateOrderSpocSwitch($orderNumber, (int) $data['spoc_id'], (int) $request->user()->id)
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyOrderSpocSwitch(Request $request, string $orderNumber)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            $order = $this->service->verifyOrderSpocSwitch($orderNumber, $data['request_id'], $data['otp'], (int) $request->user()->id);
            return response()->json([
                'message' => 'SPOC updated successfully.',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
    
    /**
     * Resend voucher email for a delivered order.
     * POST /api/send-vouchers/orders/{id}/resend-email
     */
    public function resendEmail(string $id)
    {
        try {
            $result = $this->service->resendEmail($id);
            return response()->json(['message' => 'Delivery secret email resent successfully.', 'order' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Verify that codes in Excel match the database for a given order.
     * POST /api/send-vouchers/orders/{id}/verify
     * Body: { "code_ids": [1, 2, 3, ...] }  -- optional, if not provided, fetches all codes for the order
     */
    public function verifyOrder(Request $request, string $id)
    {
        $validated = $request->validate([
            'code_ids' => ['sometimes', 'array'],
            'code_ids.*' => ['integer', 'exists:send_voucher_codes,id'],
        ]);

        $codeIds = $validated['code_ids'] ?? null;

        // If code_ids not provided, fetch all sent codes for this order
        if ($codeIds === null) {
            $order = SendVoucherOrder::with(['items.codes'])
                ->where(function ($query) use ($id) {
                    $query->whereKey($id)
                        ->orWhere('order_number', $id);
                })
                ->firstOrFail();
            $codeIds = [];
            foreach ($order->items as $item) {
                foreach ($item->codes as $code) {
                    if (in_array($code->status, ['sent', 'used'], true)) {
                        $codeIds[] = $code->id;
                    }
                }
            }
        }

        $verification = $this->service->getVerificationData($id);
        
        // If specific code_ids were provided, verify those specifically
        if ($validated['code_ids'] ?? null) {
            $matches = $this->service->verifyCodesHash($id, $codeIds);
            $verification['provided_codes_match'] = $matches;
            $verification['provided_code_ids'] = $codeIds;
        }

        return response()->json($verification);
    }

    public function retryOrder(int $id)
    {
        try {
            $order = $this->service->retryFailedOrder($id);
            return response()->json(['message' => 'Retry initiated.', 'order' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function globalMarginOtpCacheKey(int $userId, string $requestId): string
    {
        return "send-vouchers:global-margin-otp:{$userId}:{$requestId}";
    }

    private function globalMarginColumns(): array
    {
        return Schema::hasColumn('send_voucher_products', 'is_blacklisted')
            ? ['id', 'name', 'brand', 'global_margin_percentage', 'is_blacklisted']
            : ['id', 'name', 'brand', 'global_margin_percentage'];
    }

    private function sendGlobalMarginOtpMail(array $recipients, string $otp, array $changes, array $catalogRows, string $requestedBy, \DateTimeInterface $expiresAt): void
    {
        Mail::to($recipients)->send(new GlobalMarginOtpMail($otp, $changes, $catalogRows, $requestedBy, $expiresAt));
    }

    private function approverEmails(string $groupKey): array
    {
        $group = BillingOtpApprover::where('group_key', $groupKey)->where('is_active', true)->first();
        return array_values(array_unique(array_filter($group?->emails ?: ['naveentitare52@gmail.com', 'ptitare@gmail.com'])));
    }
}
