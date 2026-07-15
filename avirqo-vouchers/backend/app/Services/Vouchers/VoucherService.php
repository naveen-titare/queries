<?php

namespace App\Services\Vouchers;

use App\Models\Customer;
use App\Models\CustomerBalanceLog;
use App\Models\CustomerSpoc;
use App\Models\CustomerVoucherHistory;
use App\Models\VoucherCode;
use App\Models\VoucherOrder;
use App\Models\VoucherOrderItem;
use App\Models\VoucherProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherService
{
    // ─── Catalog ────────────────────────────────────────────────────────────

    public function catalog(array $filters = [])
    {
        return VoucherProduct::where('is_active', true)
            ->when($filters['search'] ?? null, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('brand', 'like', "%{$s}%")
            )
            ->when($filters['usage_type'] ?? null, fn($q, $v) => $q->where('usage_type', $v))
            ->when($filters['country_code'] ?? null, fn($q, $v) => $q->where('country_code', $v))
            ->paginate(24)
            ->through(function ($product) {
                return $this->withStockInfo($product);
            });
    }

    public function getProduct(int $id): array
    {
        $product = VoucherProduct::findOrFail($id);
        return $this->withStockInfo($product);
    }

    private function withStockInfo(VoucherProduct $product): array
    {
        $data = $product->toArray();
        $stockByDenomination = [];

        foreach ($product->value_denominations ?? [] as $denom) {
            $available = $product->availableCodesCount((float) $denom);
            $stockByDenomination[(string) $denom] = [
                'denomination' => $denom,
                'available' => $available,
                'low_stock' => $available <= $product->low_stock_threshold && $available > 0,
                'out_of_stock' => $available === 0,
            ];
        }

        $data['stock'] = $stockByDenomination;
        return $data;
    }

    // ─── Order Processing ────────────────────────────────────────────────────

    /**
     * Validate cart items before showing confirmation page.
     * Returns enriched cart with availability info.
     */
    public function validateCart(array $items): array
    {
        $validated = [];
        $errors = [];

        foreach ($items as $item) {
            $product = VoucherProduct::findOrFail($item['product_id']);
            $available = $product->availableCodesCount((float) $item['denomination']);

            if ($available < $item['quantity']) {
                $errors[] = "Insufficient stock for {$product->name} at {$item['denomination']} — only {$available} available.";
            }

            $validated[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'brand' => $product->brand,
                'image_url' => $product->image_url,
                'denomination' => $item['denomination'],
                'currency_code' => $product->currency_code,
                'quantity' => $item['quantity'],
                'total_value' => $item['denomination'] * $item['quantity'],
                'available' => $available,
            ];
        }

        return ['items' => $validated, 'errors' => $errors];
    }

    /**
     * Process and send the order:
     * 1. Reserve codes
     * 2. Deduct customer balance
     * 3. Build Excel in memory
     * 4. Email to SPOC
     * 5. Mark codes sent
     * 6. Log transaction
     * No Excel file ever touches disk
     */
    public function processOrder(array $data, int $sentByUserId): VoucherOrder
    {
        return DB::transaction(function () use ($data, $sentByUserId) {
            $customer = Customer::findOrFail($data['customer_id']);
            $spoc = CustomerSpoc::findOrFail($data['spoc_id']);

            if (empty($spoc->email)) {
                throw new \Exception('Selected SPOC has no email address.');
            }

            $totalAmount = collect($data['items'])->sum(fn($i) => $i['denomination'] * $i['quantity']);
            $balanceBefore = $customer->balance;

            // Generate order number
            $orderNumber = 'AVQ-' . date('Y') . '-' . str_pad(VoucherOrder::count() + 1, 5, '0', STR_PAD_LEFT);

            // Create order
            $order = VoucherOrder::create([
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'spoc_id' => $spoc->id,
                'sent_by' => $sentByUserId,
                'total_amount' => $totalAmount,
                'customer_balance_before' => $balanceBefore,
                'customer_balance_after' => $balanceBefore - $totalAmount,
                'status' => 'processing',
                'email_sent_to' => $spoc->email,
            ]);

            $allCodes = [];

            // Process each cart item
            foreach ($data['items'] as $item) {
                $product = VoucherProduct::findOrFail($item['product_id']);

                // Lock and reserve codes
                $codes = VoucherCode::where('product_id', $product->id)
                    ->where('denomination', $item['denomination'])
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($item['quantity'])
                    ->get();

                if ($codes->count() < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}.");
                }

                // Create order item
                $orderItem = VoucherOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'denomination' => $item['denomination'],
                    'currency_code' => $product->currency_code,
                    'quantity' => $item['quantity'],
                    'total_value' => $item['denomination'] * $item['quantity'],
                ]);

                // Mark codes as reserved and link to order item
                foreach ($codes as $code) {
                    $code->update(['status' => 'reserved', 'order_item_id' => $orderItem->id]);
                    $allCodes[] = [
                        'brand' => $product->brand ?? $product->name,
                        'denomination' => $item['denomination'],
                        'currency_code' => $product->currency_code,
                        'code' => $code->getDecryptedCode(),
                        'pin' => $code->getDecryptedPin(),
                        'expiry_date' => $code->expiry_date?->format('d/m/Y') ?? 'N/A',
                        'code_id' => $code->id,
                    ];
                }

                // Log voucher history on customer
                CustomerVoucherHistory::create([
                    'customer_id' => $customer->id,
                    'voucher_name' => $product->name,
                    'denomination' => $item['denomination'],
                    'quantity' => $item['quantity'],
                    'total_deducted' => $item['denomination'] * $item['quantity'],
                    'sent_by' => $sentByUserId,
                    'sent_at' => now(),
                ]);
            }

            // Deduct customer balance and log it
            $customer->decrement('balance', $totalAmount);
            $customer->refresh();

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $totalAmount,
                'balance_after' => $customer->balance,
                'note' => "Voucher order {$orderNumber}",
                'done_by' => $sentByUserId,
            ]);

            // Build Excel in memory and email
            $excelContent = $this->buildExcelInMemory($allCodes, $orderNumber);

            app(\App\Mail\VoucherOrderMail::class);
            \Illuminate\Support\Facades\Mail::to($spoc->email)
                ->send(new \App\Mail\VoucherOrderMail($order, $customer, $spoc, $excelContent, $orderNumber));

            // Unset excel from memory immediately after sending
            unset($excelContent);

            // Mark all codes as sent
            $codeIds = collect($allCodes)->pluck('code_id')->toArray();
            VoucherCode::whereIn('id', $codeIds)->update(['status' => 'sent']);

            // Mark order as sent
            $order->update(['status' => 'sent', 'sent_at' => now()]);

            return $order->load(['items.product', 'customer', 'spoc', 'sentBy']);
        });
    }

    /**
     * Build Excel entirely in memory using PhpSpreadsheet.
     * Returns raw file content as string — never written to disk.
     */
    private function buildExcelInMemory(array $codes, string $orderNumber): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vouchers');

        // Headers
        $headers = ['Brand Name', 'Denomination', 'Currency', 'Voucher Code', 'PIN', 'Expiry Date'];
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Data rows
        foreach ($codes as $row => $code) {
            $r = $row + 2;
            $sheet->setCellValue("A{$r}", $code['brand']);
            $sheet->setCellValue("B{$r}", $code['denomination']);
            $sheet->setCellValue("C{$r}", $code['currency_code']);
            $sheet->setCellValue("D{$r}", $code['code']);
            $sheet->setCellValue("E{$r}", $code['pin'] ?? '');
            $sheet->setCellValue("F{$r}", $code['expiry_date']);
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Write to memory stream
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        // Destroy spreadsheet from memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return $content;
    }

    // ─── Order History ───────────────────────────────────────────────────────

    public function orderHistory(array $filters = [])
    {
        return VoucherOrder::with(['customer', 'spoc', 'sentBy', 'items.product'])
            ->when($filters['customer_id'] ?? null, fn($q, $v) => $q->where('customer_id', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);
    }
}
