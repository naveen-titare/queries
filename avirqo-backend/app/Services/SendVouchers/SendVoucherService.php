<?php

namespace App\Services\SendVouchers;

use App\Jobs\SendVoucherEmailJob;
use App\Mail\SendVoucherOrderMail;
use App\Mail\OrderOtpMail;
use App\Models\Customer; // <-- REFERENCE ONLY, from avirqo-customers module, NOT MODIFIED
use App\Models\CustomerBalanceLog; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\CustomerSpoc; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\CustomerVoucherHistory; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\SendVoucherCode;
use App\Models\SendVoucherOrder;
use App\Models\SendVoucherOrderItem;
use App\Models\SendVoucherProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SendVoucherService - FINAL VERSION compatible with avirqo-customers module
 * 
 * Customer Module Reference (NO CHANGES MADE):
 * - Customer model: id, company_name, location, gst_number, registration_number, status, balance, softDeletes
 *   Relations: spocs(), documents(), balanceLogs(), voucherHistory()
 * - CustomerSpoc: id, customer_id FK cascade, name, email, phone, is_primary, user_id nullable, timestamps
 * - CustomerBalanceLog: id, customer_id FK cascade, type enum credit/debit, amount, balance_after, note, done_by FK users, timestamps
 * - CustomerVoucherHistory: id, customer_id FK cascade, voucher_name, denomination, quantity, total_deducted, sent_by FK users, sent_at timestamp
 * 
 * This service ONLY reads customer module and deducts balance (as required). No schema changes to customer tables.
 */
class SendVoucherService
{
    // ─── Catalog ────────────────────────────────────────────────────────────

    public function catalog(array $filters = [])
    {
        return SendVoucherProduct::where('is_active', true)
            ->when($filters['search'] ?? null, fn($q, $s) =>
                $q->where(function($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('brand', 'like', "%{$s}%");
                })
            )
            ->when($filters['usage_type'] ?? null, fn($q, $v) => $q->where('usage_type', $v))
            ->when($filters['country_code'] ?? null, fn($q, $v) => $q->where('country_code', $v))
            ->paginate(24)
            ->through(fn($product) => $this->withStockInfo($product));
    }

    public function getProduct(int $id): array
    {
        $product = SendVoucherProduct::findOrFail($id);
        return $this->withStockInfo($product);
    }

    private function withStockInfo(SendVoucherProduct $product): array
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

    // ─── Order Validation ───────────────────────────────────────────────────

    public function validateCart(array $items): array
    {
        $validated = [];
        $errors = [];
        foreach ($items as $item) {
            $product = SendVoucherProduct::findOrFail($item['product_id']);
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
     * Process order with compatibility to avirqo-customers module
     * NO CHANGES to customer module, only reference
     */
    public function processOrder(array $data, int $sentByUserId): SendVoucherOrder
    {
        // PHASE 1 - FAST transaction, no email inside
        $order = DB::transaction(function () use ($data, $sentByUserId) {
            // ── Reference Customer Module (READ-ONLY except balance) ──
            // Load customer with spocs as CustomerController::show does: load(['spocs', ...])
            $customer = Customer::with('spocs')->findOrFail($data['customer_id']);
            $spoc = CustomerSpoc::findOrFail($data['spoc_id']);

            // Customer module compatibility checks (without modifying customer module)
            // 1. SPOC belongs to customer - prevents cross-customer leak
            if ((int)$spoc->customer_id !== (int)$customer->id) {
                throw new \Exception('Selected SPOC does not belong to selected customer.');
            }
            // 2. SPOC email exists & valid (customer module validates email on create, but double-check)
            if (empty($spoc->email) || !filter_var($spoc->email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('SPOC email invalid or missing: ' . ($spoc->email ?? 'empty'));
            }
            // 3. Optional: Only allow active customers (as per Customer status enum active/on_hold/inactive)
            // 3. SPOC must be ACTIVE (only active SPOCs can receive vouchers)
            if ($spoc->status !== 'active') {
                throw new \Exception("Selected SPOC is not active (status: {$spoc->status}). Only active SPOCs can receive vouchers.");
            }
            // If you want to allow on_hold too, change to !in_array(...)
            if ($customer->status !== 'active') {
                throw new \Exception("Customer is not active (status: {$customer->status}). Only active customers can receive vouchers.");
            }
            // 4. Balance check - same logic as CustomerService::adjustBalance throws on insufficient
            // Customer module: CustomerService::adjustBalance checks if balance < amount for debit
            // Here we follow same rule but configurable - block negative balances
            $totalAmount = collect($data['items'])->sum(fn($i) => $i['denomination'] * $i['quantity']);
            $totalCodesCount = collect($data['items'])->sum(fn($i) => $i['quantity']);
            $balanceBefore = $customer->balance;

            // STRICT MODE (compatible with CustomerService): Block if insufficient
            // If you want to allow negative with warning (old behavior), comment this block
            if ($balanceBefore < $totalAmount) {
                throw new \Exception("Insufficient customer balance. Available: ₹{$balanceBefore}, Required: ₹{$totalAmount}. Please credit balance via Customers module first.");
            }

            $order = SendVoucherOrder::create([
                'order_number' => 'TEMP-' . uniqid(),
                'customer_id' => $customer->id,
                'spoc_id' => $spoc->id,
                'sent_by' => $sentByUserId,
                'total_amount' => $totalAmount,
                'customer_balance_before' => $balanceBefore,
                'customer_balance_after' => $balanceBefore - $totalAmount,
                'status' => 'processing',
                'email_sent_to' => $spoc->email,
                'total_codes_count' => $totalCodesCount,
            ]);

            // FIX #5 Safe order number: use ID, not count()
            $safeOrderNumber = SendVoucherOrder::generateOrderNumber($order->id);
            $order->update(['order_number' => $safeOrderNumber]);

            foreach ($data['items'] as $item) {
                $product = SendVoucherProduct::findOrFail($item['product_id']);
                $codes = SendVoucherCode::where('product_id', $product->id)
                    ->where('denomination', $item['denomination'])
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($item['quantity'])
                    ->get();

                if ($codes->count() < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name} at {$item['denomination']} — requested {$item['quantity']}, only {$codes->count()} available.");
                }

                $orderItem = SendVoucherOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'denomination' => $item['denomination'],
                    'currency_code' => $product->currency_code,
                    'quantity' => $item['quantity'],
                    'total_value' => $item['denomination'] * $item['quantity'],
                ]);

                foreach ($codes as $code) {
                    $code->update(['status' => 'reserved', 'order_item_id' => $orderItem->id]);
                }

                // Log to customer_voucher_history - same fields as Customer module
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

            // Deduct balance - same as CustomerService::adjustBalance does
            $customer->decrement('balance', $totalAmount);
            $customer->refresh();

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $totalAmount,
                'balance_after' => $customer->balance,
                'note' => "Send Voucher order {$order->order_number} to {$spoc->email}",
                'done_by' => $sentByUserId,
            ]);

            return $order;
        });

        // PHASE 2 - Outside transaction: build Excel + send email
        try {
            $this->sendOrderEmail($order->id);
            return $order->fresh()->load(['items.product', 'customer', 'spoc', 'sentBy']);
        } catch (\Exception $e) {
            Log::error("SendVoucher Email Failed Order {$order->order_number}: " . $e->getMessage(), ['order_id' => $order->id]);
            $this->markOrderFailed($order->id, $e->getMessage());
            throw new \Exception("Order {$order->order_number} created but email failed: " . $e->getMessage() . ". Balance restored, codes returned. Please retry.");
        }
    }

    /**
     * Step 1: Initiate order - validates, reserves codes, deducts balance, generates OTP
     * Sends OTP email to configured recipients with order summary
     * Returns order in 'pending_otp' status
     */
    public function initiateOrder(array $data, int $sentByUserId): SendVoucherOrder
    {
        // PHASE 1 - Validate and reserve codes, deduct balance
        $order = DB::transaction(function () use ($data, $sentByUserId) {
            $customer = Customer::with('spocs')->findOrFail($data['customer_id']);
            $spoc = CustomerSpoc::findOrFail($data['spoc_id']);

            // Validations
            if ((int)$spoc->customer_id !== (int)$customer->id) {
                throw new \Exception('Selected SPOC does not belong to selected customer.');
            }
            if (empty($spoc->email) || !filter_var($spoc->email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('SPOC email invalid or missing: ' . ($spoc->email ?? 'empty'));
            }
            if ($spoc->status !== 'active') {
                throw new \Exception("Selected SPOC is not active (status: {$spoc->status}). Only active SPOCs can receive vouchers.");
            }
            if ($customer->status !== 'active') {
                throw new \Exception("Customer is not active (status: {$customer->status}). Only active customers can receive vouchers.");
            }

            $totalAmount = collect($data['items'])->sum(fn($i) => $i['denomination'] * $i['quantity']);
            $totalCodesCount = collect($data['items'])->sum(fn($i) => $i['quantity']);
            $balanceBefore = $customer->balance;

            // STRICT: Block negative balance for orders
            if ($balanceBefore < $totalAmount) {
                throw new \Exception("Insufficient customer balance. Available: ₹{$balanceBefore}, Required: ₹{$totalAmount}. Please credit balance via Customers module first.");
            }

            $order = SendVoucherOrder::create([
                'order_number' => 'TEMP-' . uniqid(),
                'customer_id' => $customer->id,
                'spoc_id' => $spoc->id,
                'sent_by' => $sentByUserId,
                'total_amount' => $totalAmount,
                'customer_balance_before' => $balanceBefore,
                'customer_balance_after' => $balanceBefore - $totalAmount,
                'status' => 'pending_otp',
                'email_sent_to' => $spoc->email,
                'total_codes_count' => $totalCodesCount,
            ]);

            $safeOrderNumber = SendVoucherOrder::generateOrderNumber($order->id);
            $order->update(['order_number' => $safeOrderNumber]);

            foreach ($data['items'] as $item) {
                $product = SendVoucherProduct::findOrFail($item['product_id']);
                $codes = SendVoucherCode::where('product_id', $product->id)
                    ->where('denomination', $item['denomination'])
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($item['quantity'])
                    ->get();

                if ($codes->count() < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name} at {$item['denomination']} — requested {$item['quantity']}, only {$codes->count()} available.");
                }

                $orderItem = SendVoucherOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'denomination' => $item['denomination'],
                    'currency_code' => $product->currency_code,
                    'quantity' => $item['quantity'],
                    'total_value' => $item['denomination'] * $item['quantity'],
                ]);

                foreach ($codes as $code) {
                    $code->update(['status' => 'reserved', 'order_item_id' => $orderItem->id]);
                }

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

            // Deduct balance
            $customer->decrement('balance', $totalAmount);
            $customer->refresh();

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $totalAmount,
                'balance_after' => $customer->balance,
                'note' => "Send Voucher order {$order->order_number} to {$spoc->email}",
                'done_by' => $sentByUserId,
            ]);

            return $order;
        });

        // PHASE 2 - Generate and send OTP
        try {
            $this->sendOrderOtpEmail($order->id);
            return $order->fresh()->load(['items.product', 'customer', 'spoc', 'sentBy']);
        } catch (\Exception $e) {
            Log::error("SendVoucher OTP Email Failed Order {$order->order_number}: " . $e->getMessage(), ['order_id' => $order->id]);
            // Rollback on OTP email failure
            $this->markOrderFailed($order->id, $e->getMessage());
            throw new \Exception("Order {$order->order_number} created but OTP email failed: " . $e->getMessage() . ". Balance restored, codes released. Please retry.");
        }
    }

    /**
     * Send OTP email with order summary to configured recipients
     * Recipients: SPOC email + naveentitare52@gmail.com + ptitare@gmail.com
     */
    public function sendOrderOtpEmail(int $orderId): void
    {
        $order = SendVoucherOrder::with(['items.product', 'customer', 'spoc'])->findOrFail($orderId);
        if ($order->status !== 'pending_otp') {
            throw new \Exception("Order is not in pending_otp status.");
        }

        // Generate OTP on customer
        $customer = $order->customer;
        $otp = $customer->generateOrderOtp();

        // Build order summary for email
        $itemsSummary = [];
        foreach ($order->items as $item) {
            $itemsSummary[] = [
                'product' => $item->product->name,
                'brand' => $item->product->brand,
                'denomination' => $item->denomination,
                'currency' => $item->currency_code,
                'quantity' => $item->quantity,
                'total' => $item->denomination * $item->quantity,
            ];
        }

        // Recipients: SPOC email + fixed admin emails
        $recipients = [
            $order->spoc->email,
            'naveentitare52@gmail.com',
            'ptitare@gmail.com',
        ];
        $recipients = array_unique(array_filter($recipients));

        // Send OTP email to all recipients
        foreach ($recipients as $email) {
            Mail::to($email)->send(new OrderOtpMail($order, $order->customer, $order->spoc, $otp, $itemsSummary));
        }

        $order->update([
            'email_sent_to' => implode(', ', $recipients),
            'email_attempts' => DB::raw('email_attempts + 1'),
        ]);

        Log::info("Order OTP sent for {$order->order_number} to: " . implode(', ', $recipients));
    }

    /**
     * Step 2: Verify OTP and complete order
     * If valid, sends actual voucher email with Excel attachment
     */
    public function verifyOrderOtp(int $orderId, string $otp, int $verifiedBy): SendVoucherOrder
    {
        $order = SendVoucherOrder::with(['items.product', 'customer', 'spoc'])->findOrFail($orderId);
        
        if ($order->status !== 'pending_otp') {
            throw new \Exception("Order is not awaiting OTP verification. Current status: {$order->status}");
        }

        $customer = $order->customer;
        
        // Verify OTP
        if (!$customer->verifyOrderOtp($otp, $verifiedBy)) {
            throw new \Exception('Invalid or expired OTP.');
        }

        // Send actual voucher email with Excel
        try {
            $this->sendOrderEmail($order->id);
            return $order->fresh()->load(['items.product', 'customer', 'spoc', 'sentBy']);
        } catch (\Exception $e) {
            Log::error("SendVoucher Email Failed after OTP Order {$order->order_number}: " . $e->getMessage(), ['order_id' => $order->id]);
            // On failure after OTP verification, we don't auto-rollback as OTP was valid
            // Order stays in pending_otp, can retry
            throw new \Exception("OTP verified but voucher email failed: " . $e->getMessage() . ". Please retry sending vouchers.");
        }
    }

    /**
     * Resend OTP for an order
     */
    public function resendOrderOtp(int $orderId): SendVoucherOrder
    {
        $order = SendVoucherOrder::findOrFail($orderId);
        
        if ($order->status !== 'pending_otp') {
            throw new \Exception("Can only resend OTP for orders in pending_otp status.");
        }

        $this->sendOrderOtpEmail($order->id);
        return $order->fresh();
    }

    /**
     * Cancel order and restore balance
     */
    public function cancelOrder(string $orderNumber): SendVoucherOrder
    {
        $order = SendVoucherOrder::where('order_number', $orderNumber)->firstOrFail();
        
        if (!in_array($order->status, ['pending_otp', 'processing'])) {
            throw new \Exception("Can only cancel orders in pending_otp or processing status.");
        }

        DB::transaction(function () use ($order) {
            // Release reserved codes
            $itemIds = $order->items()->pluck('id');
            SendVoucherCode::whereIn('order_item_id', $itemIds)->where('status','reserved')->update(['status'=>'available','order_item_id'=>null]);

            // Restore customer balance
            $customer = $order->customer;
            $customer->increment('balance', $order->total_amount);

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $order->total_amount,
                'balance_after' => $customer->fresh()->balance,
                'note' => "Cancelled Send Voucher order {$order->order_number}",
                'done_by' => $order->sent_by,
            ]);

            $order->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelled by user before OTP verification',
                'email_attempts' => DB::raw('email_attempts + 1'),
            ]);
        });

        return $order->fresh();
    }

    public function sendOrderEmail(int $orderId): void
    {
        $order = SendVoucherOrder::with(['items.product', 'customer', 'spoc'])->findOrFail($orderId);
        if ($order->status === 'sent') {
            throw new \Exception("Order already sent.");
        }

        $allCodes = [];
        $codeIds = [];
        foreach ($order->items as $item) {
            $codes = SendVoucherCode::where('order_item_id', $item->id)->where('status', 'reserved')->get();
            if ($codes->isEmpty()) {
                $codes = SendVoucherCode::where('order_item_id', $item->id)->whereIn('status', ['reserved','sent'])->get();
            }
            foreach ($codes as $code) {
                $allCodes[] = [
                    'brand' => $item->product->brand ?? $item->product->name,
                    'product_name' => $item->product->name,
                    'denomination' => $item->denomination,
                    'currency_code' => $item->currency_code,
                    'code' => $code->getDecryptedCode(),
                    'pin' => $code->getDecryptedPin(),
                    'expiry_date' => $code->expiry_date?->format('d/m/Y') ?? 'N/A',
                    'code_id' => $code->id,
                ];
                $codeIds[] = $code->id;
            }
        }

        if (empty($allCodes)) {
            throw new \Exception("No codes found for order {$order->order_number}");
        }

        $maxCodesPerEmail = config('send-vouchers.max_codes_per_email', 5000);
        if (count($allCodes) > $maxCodesPerEmail) {
            Log::warning("Large Send Voucher Order {$order->order_number} has " . count($allCodes) . " codes");
        }

        $excelContent = $this->buildExcelInMemory($allCodes, $order->order_number);
        $excelSizeMb = strlen($excelContent) / 1024 / 1024;
        Log::info("Excel built Order {$order->order_number}: {$excelSizeMb} MB, " . count($allCodes) . " codes");

        $maxAttachmentMb = config('send-vouchers.max_attachment_mb', 18);
        if ($excelSizeMb > $maxAttachmentMb) {
            throw new \Exception("Excel size {$excelSizeMb} MB exceeds mail limit {$maxAttachmentMb} MB for " . count($allCodes) . " codes. Split order or use secure link.");
        }

        try {
            Mail::to($order->spoc->email)->send(new SendVoucherOrderMail($order, $order->customer, $order->spoc, $excelContent, $order->order_number));

            // Compute verification hash from code IDs (sorted for consistency)
            $codesHash = $this->computeCodesHash($codeIds);

            DB::transaction(function () use ($order, $codeIds, $codesHash) {
                SendVoucherCode::whereIn('id', $codeIds)->update(['status' => 'sent']);
                $order->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'email_attempts' => DB::raw('email_attempts + 1'),
                    'failure_reason' => null,
                    'codes_hash' => $codesHash,
                ]);
            });

            Log::info("Order {$order->order_number} sent to {$order->email_sent_to} [codes_hash: {$codesHash}]");
        } finally {
            unset($excelContent);
        }
        unset($allCodes);
    }

    /**
     * Compute SHA256 hash of sorted code IDs for verification.
     * This allows anyone to verify that the codes in the Excel match the database.
     * Usage: hash('sha256', implode(',', $sortedCodeIds))
     */
    private function computeCodesHash(array $codeIds): string
    {
        sort($codeIds);
        return hash('sha256', implode(',', $codeIds));
    }

    /**
     * Verify that the provided code IDs match the order's codes_hash.
     * Returns true if they match, false otherwise.
     */
    public function verifyCodesHash(int $orderId, array $codeIds): bool
    {
        $order = SendVoucherOrder::findOrFail($orderId);
        if (!$order->codes_hash) {
            return false; // No hash stored (legacy order)
        }
        $computedHash = $this->computeCodesHash($codeIds);
        return hash_equals($order->codes_hash, $computedHash);
    }

    /**
     * Get verification details for an order - returns code IDs and hash for manual verification.
     */
    public function getVerificationData(int $orderId): array
    {
        $order = SendVoucherOrder::with(['items.codes'])->findOrFail($orderId);
        
        $codeIds = [];
        $codesDetail = [];
        
        foreach ($order->items as $item) {
            foreach ($item->codes as $code) {
                $codeIds[] = $code->id;
                $codesDetail[] = [
                    'code_id' => $code->id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'brand' => $item->product->brand ?? 'N/A',
                    'denomination' => $item->denomination,
                    'status' => $code->status,
                ];
            }
        }
        
        sort($codeIds);
        $computedHash = $this->computeCodesHash($codeIds);
        
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'stored_hash' => $order->codes_hash,
            'computed_hash' => $computedHash,
            'matches' => $order->codes_hash ? hash_equals($order->codes_hash, $computedHash) : null,
            'total_codes' => count($codeIds),
            'codes' => $codesDetail,
        ];
    }

    public function markOrderFailed(int $orderId, string $reason): void
    {
        DB::transaction(function () use ($orderId, $reason) {
            $order = SendVoucherOrder::with('customer')->findOrFail($orderId);
            $itemIds = $order->items()->pluck('id');
            SendVoucherCode::whereIn('order_item_id', $itemIds)->where('status','reserved')->update(['status'=>'available','order_item_id'=>null]);

            $customer = $order->customer;
            $customer->increment('balance', $order->total_amount);

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $order->total_amount,
                'balance_after' => $customer->fresh()->balance,
                'note' => "Restore failed Send Voucher order {$order->order_number}: $reason",
                'done_by' => $order->sent_by,
            ]);

            $order->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'email_attempts' => DB::raw('email_attempts + 1'),
            ]);
        });
    }

    public function retryFailedOrder(int $orderId): SendVoucherOrder
    {
        $order = SendVoucherOrder::findOrFail($orderId);
        if (!in_array($order->status, ['failed','partially_failed'])) {
            throw new \Exception("Only failed orders can be retried.");
        }
        $order->update(['status'=>'processing','failure_reason'=>null]);
        $this->sendOrderEmail($order->id);
        return $order->fresh()->load(['items.product','customer','spoc','sentBy']);
    }

    private function buildExcelInMemory(array $codes, string $orderNumber): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Sheet 1: Vouchers (main data)
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vouchers');

        $headers = ['Brand Name','Product','Denomination','Currency','Voucher Code','PIN','Expiry Date','Code ID (for verification)'];
        foreach ($headers as $col => $h) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1).'1';
            $sheet->setCellValue($cell, $h);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D9E75');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        foreach ($codes as $r => $c) {
            $row = $r+2;
            $sheet->setCellValue("A{$row}", $c['brand']);
            $sheet->setCellValue("B{$row}", $c['product_name']);
            $sheet->setCellValue("C{$row}", $c['denomination']);
            $sheet->setCellValue("D{$row}", $c['currency_code']);
            $sheet->setCellValue("E{$row}", $c['code']);
            $sheet->setCellValue("F{$row}", $c['pin'] ?? '');
            $sheet->setCellValue("G{$row}", $c['expiry_date']);
            // Code ID column (H) - for verification against database
            $sheet->setCellValue("H{$row}", $c['code_id'] ?? '');
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('@');
        }

        foreach (range('A','H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        // Hide the Code ID column by default (user can unhide if needed)
        $sheet->getColumnDimension('H')->setVisible(false);

        // Sheet 2: Verification Info
        $verifySheet = $spreadsheet->createSheet();
        $verifySheet->setTitle('Verification');
        
        $verifyHeaders = ['Field', 'Value'];
        foreach ($verifyHeaders as $col => $h) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1).'1';
            $verifySheet->setCellValue($cell, $h);
            $verifySheet->getStyle($cell)->getFont()->setBold(true);
            $verifySheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D9E75');
            $verifySheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }
        
        $verifyData = [
            ['Order Number', $orderNumber],
            ['Total Codes', count($codes)],
            ['Verification Hash (SHA256)', $this->computeCodesHash(array_column($codes, 'code_id'))],
            ['Generated At', now()->format('Y-m-d H:i:s')],
            ['', ''],
            ['Instructions', ''],
            ['1. Copy the "Code ID (for verification)" column from Vouchers sheet', ''],
            ['2. Use API: POST /api/send-vouchers/orders/{id}/verify', ''],
            ['3. Body: { "code_ids": [comma-separated IDs] }', ''],
            ['4. Response will confirm if codes match database', ''],
        ];
        
        foreach ($verifyData as $r => $rowData) {
            $row = $r + 2;
            $verifySheet->setCellValue("A{$row}", $rowData[0]);
            $verifySheet->setCellValue("B{$row}", $rowData[1]);
        }
        
        $verifySheet->getColumnDimension('A')->setAutoSize(true);
        $verifySheet->getColumnDimension('B')->setWidth(80);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
        return $content;
    }

    public function orderHistory(array $filters = [])
{
    return SendVoucherOrder::with(['customer','spoc','sentBy','items.product'])
        ->when($filters['customer_id'] ?? null, fn($q,$v) => $q->where('customer_id',$v))
        ->when($filters['status'] ?? null, fn($q,$v) => $q->where('status',$v))
        ->when($filters['search'] ?? null, fn($q,$s) =>
            $q->where('order_number','like',"%{$s}%")
              ->orWhereHas('customer', fn($cq) => $cq->where('company_name','like',"%{$s}%"))
              ->orWhereHas('spoc', fn($sq) => $sq->where('name','like',"%{$s}%"))
        )
        ->when($filters['date_from'] ?? null, fn($q,$v) => $q->whereDate('created_at','>=',$v))
        ->when($filters['date_to'] ?? null, fn($q,$v) => $q->whereDate('created_at','<=',$v))
        ->latest()
        ->paginate(20);
}
    
     /**
     * Resend voucher email for a delivered order.
     * Rebuilds the Excel from stored encrypted codes and resends.
     * No codes are exposed outside this method.
     */
    public function resendEmail(int $orderId): SendVoucherOrder
    {
        $order = SendVoucherOrder::with([
            'customer', 'spoc', 'sentBy', 'items.product', 'items.codes'
        ])->findOrFail($orderId);

        if ($order->status === 'cancelled') {
            throw new \Exception('Cannot resend email for a cancelled order.');
        }

        if (empty($order->spoc->email)) {
            throw new \Exception('SPOC has no email address on record.');
        }

        // Rebuild Excel in memory from stored encrypted codes
        $allCodes = [];
        foreach ($order->items as $item) {
            foreach ($item->codes as $code) {
                $allCodes[] = [
                        'brand'         => $item->product->brand ?? $item->product->name,
                        'product_name'  => $item->product->name ?? 'N/A',
                        'denomination'  => $item->denomination,
                        'currency_code' => $item->currency_code,
                        'code'          => $code->getDecryptedCode(),
                        'pin'           => $code->getDecryptedPin(),
                        'expiry_date'   => $code->expiry_date?->format('d/m/Y') ?? 'N/A',
                        'code_id'       => (string) $code->id,
];
            }
        }

        if (empty($allCodes)) {
            throw new \Exception('No voucher codes found for this order.');
        }

        $excelContent = $this->buildExcelInMemory($allCodes, $order->order_number);

        \Illuminate\Support\Facades\Mail::to($order->spoc->email)
            ->send(new \App\Mail\SendVoucherOrderMail(
                $order,
                $order->customer,
                $order->spoc,
                $excelContent,
                $order->order_number
            ));

        unset($excelContent);

        // Update email attempts count and mark as delivered if it was pending
        $order->increment('email_attempts');
        if (in_array($order->status, ['pending_otp', 'processing', 'failed', 'partially_failed'])) {
            $order->update(['status' => 'sent', 'sent_at' => now()]);
            }

        return $order->fresh(['customer', 'spoc', 'sentBy', 'items.product']);
    }


    public function dispatchAsync(int $orderId): void
    {
        SendVoucherEmailJob::dispatch($orderId)->onQueue('send-vouchers');
    }
}