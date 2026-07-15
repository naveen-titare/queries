<?php

namespace App\Http\Controllers\Api\Vouchers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Vouchers\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(protected VoucherService $service) {}

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
            'items.*.product_id' => ['required', 'integer', 'exists:voucher_products,id'],
            'items.*.denomination' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->service->validateCart($data['items']);

        if (!empty($result['errors'])) {
            return response()->json(['message' => 'Cart validation failed.', 'errors' => $result['errors']], 422);
        }

        return response()->json($result);
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'spoc_id' => ['required', 'integer', 'exists:customer_spocs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:voucher_products,id'],
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
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function orderHistory(Request $request)
    {
        return response()->json(
            $this->service->orderHistory($request->only('customer_id', 'status'))
        );
    }

    public function orderDetail(int $id)
    {
        $order = \App\Models\VoucherOrder::with(['customer', 'spoc', 'sentBy', 'items.product'])
            ->findOrFail($id);
        return response()->json($order);
    }
}
