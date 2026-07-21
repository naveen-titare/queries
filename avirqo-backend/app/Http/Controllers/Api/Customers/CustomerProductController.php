<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerProduct;
use App\Models\SendVoucherProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerProductController extends Controller
{
    /** Product configuration for a customer, including unconfigured catalogue products. */
    public function index(Customer $customer, Request $request)
    {
        $configured = CustomerProduct::where('customer_id', $customer->id)
            ->get()->keyBy('product_id');

        $products = SendVoucherProduct::query()
            ->where('is_active', true)
            ->where('is_blacklisted', false)
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->orderBy('brand')
            ->get()
            ->map(function (SendVoucherProduct $product) use ($configured) {
                $setting = $configured->get($product->id);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'image_url' => $product->image_url,
                    'currency_code' => $product->currency_code,
                    'discount_percentage' => $setting ? (float) $setting->discount_percentage : 0,
                    'is_blacklisted' => $setting?->is_blacklisted ?? false,
                ];
            })->values();

        return response()->json(['customer_id' => $customer->id, 'data' => $products]);
    }

    /** Replaces a customer's product exceptions (discounts and blacklist) atomically. */
    public function update(Customer $customer, Request $request)
    {
        $data = $request->validate([
            'products' => ['present', 'array'],
            'products.*.product_id' => ['required', 'integer', 'distinct', 'exists:send_voucher_products,id'],
            'products.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'products.*.is_blacklisted' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($customer, $data) {
            $productIds = collect($data['products'])->pluck('product_id')->all();
            $settings = CustomerProduct::where('customer_id', $customer->id);
            if ($productIds) {
                $settings->whereNotIn('product_id', $productIds)->delete();
            } else {
                $settings->delete();
            }

            foreach ($data['products'] as $item) {
                CustomerProduct::updateOrCreate(
                    ['customer_id' => $customer->id, 'product_id' => $item['product_id']],
                    [
                        'discount_percentage' => $item['discount_percentage'] ?? 0,
                        'is_blacklisted' => $item['is_blacklisted'] ?? false,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Customer products saved successfully.']);
    }

    /** Catalogue for other modules: every active product except customer-blacklisted products. */
    public function available(Customer $customer)
    {
        $settings = CustomerProduct::where('customer_id', $customer->id)->get()->keyBy('product_id');
        $products = SendVoucherProduct::where('is_active', true)->where('is_blacklisted', false)->orderBy('brand')->orderBy('name')->get()
            ->reject(fn (SendVoucherProduct $product) => $settings->get($product->id)?->is_blacklisted)
            ->map(fn (SendVoucherProduct $product) => [
                'product' => $product,
                'discount_percentage' => (float) ($settings->get($product->id)?->discount_percentage ?? 0),
            ])->values();

        return response()->json(['customer_id' => $customer->id, 'data' => $products]);
    }
}
