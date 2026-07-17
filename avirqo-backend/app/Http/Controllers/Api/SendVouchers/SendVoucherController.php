<?php

namespace App\Http\Controllers\Api\SendVouchers;

use App\Http\Controllers\Controller;
use App\Models\SendVoucherOrder;
use App\Services\SendVouchers\SendVoucherService;
use Illuminate\Http\Request;

class SendVoucherController extends Controller
{
    public function __construct(protected SendVoucherService $service) {}

    public function catalog(Request $request)
    {
        return response()->json(
            $this->service->catalog($request->only('search', 'usage_type', 'country_code'))
        );
    }

    public function show(int $id)
    {
        return response()->json($this->service->getProduct($id));
    }

    public function validateCart(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:send_voucher_products,id'],
            'items.*.denomination' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->service->validateCart($data['items']);

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
    public function verifyOrderOtp(Request $request, int $id)
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            $order = $this->service->verifyOrderOtp($id, $data['otp'], $request->user()->id);
            return response()->json([
                'message' => 'OTP verified. Vouchers sent successfully.',
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
    public function resendOrderOtp(int $id)
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
     * POST /api/send-vouchers/orders/{id}/cancel
     */
    public function cancelOrder(int $id)
    {
        try {
            $order = $this->service->cancelOrder($id);
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

    public function orderDetail(int $id)
    {
        $order = SendVoucherOrder::with(['customer', 'spoc', 'sentBy', 'items.product', 'items.codes'])
            ->findOrFail($id);
        return response()->json($order);
    }
    
    /**
     * Resend voucher email for a delivered order.
     * POST /api/send-vouchers/orders/{id}/resend-email
     */
    public function resendEmail(int $id)
    {
        try {
            $result = $this->service->resendEmail($id);
            return response()->json(['message' => 'Voucher email resent successfully.', 'order' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Verify that codes in Excel match the database for a given order.
     * POST /api/send-vouchers/orders/{id}/verify
     * Body: { "code_ids": [1, 2, 3, ...] }  -- optional, if not provided, fetches all codes for the order
     */
    public function verifyOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'code_ids' => ['sometimes', 'array'],
            'code_ids.*' => ['integer', 'exists:send_voucher_codes,id'],
        ]);

        $codeIds = $validated['code_ids'] ?? null;

        // If code_ids not provided, fetch all sent codes for this order
        if ($codeIds === null) {
            $order = SendVoucherOrder::with(['items.codes'])->findOrFail($id);
            $codeIds = [];
            foreach ($order->items as $item) {
                foreach ($item->codes as $code) {
                    if ($code->status === 'sent') {
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
}