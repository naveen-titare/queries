<?php

namespace App\Services\Vouchers;

use Illuminate\Support\Facades\DB;
use App\Models\VoucherInventory;
use App\Models\VoucherCode;
use App\Models\VoucherImportLog;
use RuntimeException;

/**
 * Orchestrates importing vouchers from Xoxoday into Avirqo's inventory.
 * Enforces the balance check, places the order, stores codes, and logs
 * every attempt (success or error) with the selection snapshot.
 */
class VoucherImportService
{
    public function __construct(private XoxodayClient $xoxoday) {}

    /**
     * @param  array  $selection  ['product_id','brand_name','denomination','quantity','image_url'?]
     */
    public function import(array $selection, ?int $userId = null): VoucherImportLog
    {
        $productId    = (int) $selection['product_id'];
        $brand        = $selection['brand_name'] ?? ('Product #' . $productId);
        $denomination = (float) $selection['denomination'];
        $quantity     = max(1, (int) $selection['quantity']);
        $currency     = $selection['currency_code'] ?? 'INR';
        $totalValue   = $denomination * $quantity;

        $poNumber = 'AVQ-' . now()->format('YmdHis') . '-' . $productId;

        $requestSnapshot = [
            'product_id'   => $productId,
            'brand_name'   => $brand,
            'denomination' => $denomination,
            'quantity'     => $quantity,
            'currency'     => $currency,
            'po_number'    => $poNumber,
        ];

        try {
            // 1) Balance check
            $balance = $this->xoxoday->getBalance();
            $available = (float) ($balance['value'] ?? 0);
            if ($available < $totalValue) {
                throw new RuntimeException(sprintf(
                    'Insufficient Xoxoday balance. Need %s %.2f but only %s %.2f available.',
                    $currency, $totalValue, $balance['currency'] ?? $currency, $available
                ));
            }

            // 2) Place order
            $order = $this->xoxoday->placeOrder([
                'productId'    => $productId,
                'quantity'     => $quantity,
                'denomination' => $denomination,
                'poNumber'     => $poNumber,
            ]);

            $orderId  = $order['orderId'] ?? null;
            $vouchers = $order['vouchers'] ?? [];

            // 3) Persist inventory + codes atomically, then log success
            return DB::transaction(function () use (
                $selection, $productId, $brand, $denomination, $quantity, $currency,
                $totalValue, $poNumber, $orderId, $vouchers, $order, $requestSnapshot, $userId
            ) {
                $inventory = VoucherInventory::firstOrCreate(
                    ['product_id' => $productId, 'denomination' => $denomination, 'currency_code' => $currency],
                    ['brand_name' => $brand, 'image_url' => $selection['image_url'] ?? null]
                );

                $log = VoucherImportLog::create([
                    'user_id'          => $userId,
                    'product_id'       => $productId,
                    'brand_name'       => $brand,
                    'denomination'     => $denomination,
                    'quantity'         => $quantity,
                    'total_value'      => $totalValue,
                    'currency_code'    => $currency,
                    'status'           => 'success',
                    'message'          => "Imported {$quantity} x {$currency} {$denomination} {$brand} voucher(s).",
                    'xoxoday_order_id' => $orderId,
                    'po_number'        => $poNumber,
                    'request_payload'  => $requestSnapshot,
                    'response_payload' => $order,
                ]);

                $stored = 0;
                foreach ($vouchers as $v) {
                    VoucherCode::create([
                        'inventory_id'     => $inventory->id,
                        'import_log_id'    => $log->id,
                        'xoxoday_order_id' => $orderId,
                        'product_id'       => $productId,
                        'denomination'     => $v['amount'] ?? $denomination,
                        'currency_code'    => $v['currency'] ?? $currency,
                        'voucher_code'     => $v['voucherCode'] ?? null,
                        'pin'              => $v['pin'] ?? null,
                        'validity'         => isset($v['validity']) ? substr($v['validity'], 0, 10) : null,
                        'status'           => 'in_stock',
                    ]);
                    $stored++;
                }

                $inventory->addStock($stored > 0 ? $stored : $quantity);

                return $log;
            });
        } catch (\Throwable $e) {
            // Log the failure with the selection snapshot
            return VoucherImportLog::create([
                'user_id'         => $userId,
                'product_id'      => $productId,
                'brand_name'      => $brand,
                'denomination'    => $denomination,
                'quantity'        => $quantity,
                'total_value'     => $totalValue,
                'currency_code'   => $currency,
                'status'          => 'error',
                'message'         => $e->getMessage(),
                'po_number'       => $poNumber,
                'request_payload' => $requestSnapshot,
                'response_payload'=> null,
            ]);
        }
    }
}
