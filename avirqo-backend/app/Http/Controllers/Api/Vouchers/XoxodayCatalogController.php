<?php

namespace App\Http\Controllers\Api\Vouchers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Vouchers\XoxodayClient;

/**
 * Live Xoxoday catalog: filters, vouchers list, and balance.
 * Powers the "Fetch from Xoxoday" button and the selection modal.
 */
class XoxodayCatalogController extends Controller
{
    public function __construct(private XoxodayClient $xoxoday) {}

    /** Filters (country/brand/category) for building the picker. */
    public function filters(Request $request)
    {
        try {
            $group = $request->query('group', 'Country');
            return response()->json(['data' => $this->xoxoday->getFilters($group)]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /** Voucher catalog. Normalised so the frontend gets denominations as an array. */
    public function vouchers(Request $request)
    {
        try {
            $data = $this->xoxoday->getVouchers([
                'limit' => (int) $request->query('limit', 50),
                'page'  => (int) $request->query('page', 1),
            ]);

            $items = collect($data)->map(function ($v) {
                $denoms = [];
                if (! empty($v['valueDenominations'])) {
                    $denoms = array_values(array_filter(array_map(
                        fn ($d) => (float) trim($d),
                        explode(',', $v['valueDenominations'])
                    )));
                }
                return [
                    'product_id'    => $v['productId'] ?? null,
                    'brand_name'    => $v['name'] ?? '',
                    'image_url'     => $v['imageUrl'] ?? null,
                    'categories'    => $v['categories'] ?? '',
                    'currency_code' => $v['currencyCode'] ?? 'INR',
                    'country'       => $v['countryName'] ?? '',
                    'value_type'    => $v['valueType'] ?? '',
                    'min_value'     => $v['minValue'] ?? 0,
                    'max_value'     => $v['maxValue'] ?? 0,
                    'denominations' => $denoms,
                    'order_limit'   => $v['orderQuantityLimit'] ?? null,
                ];
            })->values();

            return response()->json(['data' => $items]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /** Xoxoday wallet balance for Avirqo. */
    public function balance()
    {
        try {
            return response()->json(['data' => $this->xoxoday->getBalance()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
