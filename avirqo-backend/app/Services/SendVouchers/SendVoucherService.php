<?php

namespace App\Services\SendVouchers;

use App\Jobs\SendVoucherEmailJob;
use App\Mail\OrderOtpMail;
use App\Mail\OrderDeliveryOtpMail;
use App\Mail\OrderDeliverySecretMail;
use App\Mail\OrderSpocSwitchOtpMail;
use App\Models\Customer; // <-- REFERENCE ONLY, from avirqo-customers module, NOT MODIFIED
use App\Models\CustomerBalanceLog; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\CustomerSpoc; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\CustomerVoucherHistory; // <-- REFERENCE ONLY, from avirqo-customers module
use App\Models\VoucherCampaignProduct;
use App\Models\SendVoucherCode;
use App\Models\SendVoucherOrder;
use App\Models\SendVoucherOrderItem;
use App\Models\SendVoucherProduct;
use App\Models\ProformaInvoice;
use App\Models\TaxInvoice;
use App\Services\Billing\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Hashids\Hashids;

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
    public function __construct(protected BillingService $billing) {}

    // ─── Catalog ────────────────────────────────────────────────────────────

    public function catalog(array $filters = [])
    {
        $customerId = $filters['customer_id'] ?? null;
        $campaignId = $customerId ? $this->campaignIdForCustomer((int) $customerId) : null;
        $catalog = SendVoucherProduct::where('is_active', true)
            ->where('is_blacklisted', false)
            ->when($customerId && !$campaignId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($campaignId, fn ($q) => $q->whereDoesntHave('campaignSettings', fn ($settings) =>
                $settings->where('campaign_id', $campaignId)->where('is_blacklisted', true)))
            ->when($filters['search'] ?? null, fn($q, $s) =>
                $q->where(function($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('brand', 'like', "%{$s}%");
                })
            )
            ->when($filters['usage_type'] ?? null, fn($q, $v) => $q->where('usage_type', $v))
            ->when($filters['country_code'] ?? null, fn($q, $v) => $q->where('country_code', $v))
            ->paginate(24);

        $discounts = $campaignId ? VoucherCampaignProduct::where('campaign_id', $campaignId)->pluck('discount_percentage', 'product_id') : collect();
        return $catalog->through(function ($product) use ($discounts) {
            $data = $this->withStockInfo($product);
            $data['customer_discount_percentage'] = (float) ($discounts[$product->id] ?? 0);
            return $data;
        });
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

    public function validateCart(array $items, int $customerId, string $pricingMode = 'product', ?int $proformaInvoiceId = null): array
    {
        if ($proformaInvoiceId) {
            $priced = $this->pricePiCart($items, $customerId, $proformaInvoiceId);
            return ['items' => $priced['items'], 'errors' => []];
        }

        $validated = [];
        $errors = [];
        $campaignId = $this->campaignIdForCustomer($customerId);
        if (!$campaignId) return ['items' => [], 'errors' => ['This customer is not assigned to an active voucher campaign.']];
        foreach ($items as $item) {
            $product = SendVoucherProduct::findOrFail($item['product_id']);
            if ($product->is_blacklisted) {
                $errors[] = "{$product->name} is blacklisted globally.";
                continue;
            }
            $setting = VoucherCampaignProduct::where('campaign_id', $campaignId)->where('product_id', $product->id)->first();
            if ($setting?->is_blacklisted) {
                $errors[] = "{$product->name} is blacklisted for this customer.";
                continue;
            }
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
                'gross_total' => $grossTotal = $item['denomination'] * $item['quantity'],
                'global_margin_percentage' => $marginPercentage = (float) $product->global_margin_percentage,
                'global_margin_amount' => $marginAmount = round($grossTotal * $marginPercentage / 100, 2),
                'discount_percentage' => $discountPercentage = $pricingMode === 'product' ? (float) ($setting?->discount_percentage ?? 0) : 0,
                // Positive adjustment is a discount; negative adjustment is a service charge.
                'discount_amount' => $discountAmount = round($grossTotal * $discountPercentage / 100, 2),
                'total_value' => round($grossTotal - $discountAmount, 2),
                'available' => $available,
            ];
        }
        return ['items' => $validated, 'errors' => $errors];
    }

    private function campaignIdForCustomer(int $customerId): ?int
    {
        return DB::table('voucher_campaign_customers as assignments')
            ->join('voucher_campaigns as campaigns', 'campaigns.id', '=', 'assignments.campaign_id')
            ->where('assignments.customer_id', $customerId)->where('campaigns.is_active', true)
            ->value('assignments.campaign_id');
    }

    private function pricePiCart(array $items, int $customerId, int $proformaInvoiceId): array
    {
        $pi = ProformaInvoice::with(['items.product'])
            ->whereKey($proformaInvoiceId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if (!in_array($pi->status, ['paid', 'partially_delivered'], true) || $pi->availableForDelivery() <= 0) {
            throw new \Exception('Selected PI does not have paid delivery balance available.');
        }

        $allowed = $pi->items->keyBy(fn ($item) => $item->product_id.'|'.number_format((float) $item->denomination, 2, '.', ''));
        $requestedByKey = collect($items)->groupBy(fn ($item) => $item['product_id'].'|'.number_format((float) $item['denomination'], 2, '.', ''));
        $pricedItems = [];
        $errors = [];

        foreach ($requestedByKey as $key => $rows) {
            $piItem = $allowed->get($key);
            if (!$piItem) {
                $errors[] = 'Cart contains a product or denomination that is not part of the selected PI.';
                continue;
            }

            $quantity = (int) $rows->sum('quantity');
            $deliveredQuantity = $this->lockedOrDeliveredPiQuantity($pi->id, (int) $piItem->product_id, (float) $piItem->denomination);
            $pendingQuantity = max(0, (int) $piItem->quantity - $deliveredQuantity);
            $available = $piItem->product?->availableCodesCount((float) $piItem->denomination) ?? 0;

            if ($quantity > $pendingQuantity) {
                $errors[] = "{$piItem->product_name} {$piItem->currency_code} {$piItem->denomination}: PI pending quantity is {$pendingQuantity}.";
            }
            if ($quantity > $available) {
                $errors[] = "Insufficient stock for {$piItem->product_name} at {$piItem->denomination} — only {$available} available.";
            }

            $grossTotal = round((float) $piItem->unit_price * $quantity, 2);
            $discountPercentage = ($pi->discount_type ?? 'campaign') === 'invoice' ? 0.0 : (float) $piItem->discount_percentage;
            $discountAmount = round($grossTotal * $discountPercentage / 100, 2);

            $pricedItems[] = [
                'product_id' => $piItem->product_id,
                'product_name' => $piItem->product_name,
                'brand' => $piItem->brand,
                'image_url' => $piItem->product?->image_url,
                'denomination' => (float) $piItem->denomination,
                'currency_code' => $piItem->currency_code,
                'quantity' => $quantity,
                'gross_total' => $grossTotal,
                'global_margin_percentage' => (float) ($piItem->product?->global_margin_percentage ?? 0),
                'global_margin_amount' => 0,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'total_value' => round($grossTotal - $discountAmount, 2),
                'available' => $available,
                'pending_quantity' => $pendingQuantity,
            ];
        }

        if ($errors) {
            throw new \Exception(implode(' ', $errors));
        }

        return [
            'pi' => $pi,
            'items' => $pricedItems,
            'pricing_mode' => ($pi->discount_type ?? 'campaign') === 'invoice' ? 'invoice' : 'product',
            'invoice_discount_percentage' => (float) ($pi->invoice_discount_percentage ?? 0),
        ];
    }

    private function lockedOrDeliveredPiQuantity(int $piId, int $productId, float $denomination): int
    {
        return (int) DB::table('send_voucher_order_items as items')
            ->join('send_voucher_orders as orders', 'orders.id', '=', 'items.order_id')
            ->where('orders.proforma_invoice_id', $piId)
            ->whereIn('orders.status', ['pending_otp', 'processing', 'sent', 'success'])
            ->where('items.product_id', $productId)
            ->where('items.denomination', $denomination)
            ->sum('items.quantity');
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

            $this->assertEligiblePrimarySpoc($customer, $spoc);
            // If you want to allow on_hold too, change to !in_array(...)
            if ($customer->status !== 'active') {
                throw new \Exception("Customer is not active (status: {$customer->status}). Only active customers can receive vouchers.");
            }
            // 4. Balance check - same logic as CustomerService::adjustBalance throws on insufficient
            // Customer module: CustomerService::adjustBalance checks if balance < amount for debit
            // Here we follow same rule but configurable - block negative balances
            $pricing = $this->pricePiCart($data['items'], $customer->id, (int) $data['proforma_invoice_id']);
            $pricedItems = $pricing['items'];
            $productsSubtotal = collect($pricedItems)->sum('total_value');
            $pricingMode = $pricing['pricing_mode'];
            $invoiceDiscountPercentage = $pricingMode === 'invoice' ? (float) ($pricing['invoice_discount_percentage'] ?? 0) : 0;
            $invoiceDiscountAmount = round($productsSubtotal * $invoiceDiscountPercentage / 100, 2);
            $totalAmount = $productsSubtotal - $invoiceDiscountAmount;
            $totalCodesCount = collect($pricedItems)->sum('quantity');
            $balanceBefore = $customer->balance;
            $pi = ProformaInvoice::whereKey($data['proforma_invoice_id'])
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pi->availableForDelivery() < $totalAmount) {
                throw new \Exception('Selected PI does not have enough paid available balance for this order.');
            }
            $pi = null;
            $completesProformaInvoice = false;
            if (!empty($data['proforma_invoice_id'])) {
                $pi = ProformaInvoice::whereKey($data['proforma_invoice_id'])->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
                if (!in_array($pi->status, ['paid', 'partially_delivered'], true) || $pi->availableForDelivery() < $totalAmount) {
                    throw new \Exception('Selected Proforma Invoice does not have enough paid available balance for this order.');
                }
                $completesProformaInvoice = round($totalAmount, 2) >= round($pi->availableForDelivery(), 2);
            }

            // STRICT MODE (compatible with CustomerService): Block if insufficient
            // If you want to allow negative with warning (old behavior), comment this block
            if ($balanceBefore < $totalAmount) {
                throw new \Exception("Insufficient customer balance. Available: ₹{$balanceBefore}, Required: ₹{$totalAmount}. Please credit balance via Customers module first.");
            }

            $order = SendVoucherOrder::create([
                'order_number' => 'TEMP-' . uniqid(),
                'customer_id' => $customer->id,
                'spoc_id' => $spoc->id,
                'proforma_invoice_id' => $pi?->id,
                'sent_by' => $sentByUserId,
                'total_amount' => $totalAmount,
                'pricing_mode' => $pricingMode,
                'invoice_discount_percentage' => $invoiceDiscountPercentage,
                'invoice_discount_amount' => $invoiceDiscountAmount,
                'products_subtotal' => $productsSubtotal,
                'spoc_name' => $spoc->name,
                'spoc_email' => $spoc->email,
                'spoc_phone' => $spoc->phone,
                'customer_balance_before' => $balanceBefore,
                'customer_balance_after' => $balanceBefore - $totalAmount,
                'status' => 'processing',
                'email_sent_to' => $spoc->email,
                'total_codes_count' => $totalCodesCount,
            ]);

            // FIX #5 Safe order number: use ID, not count()
            $safeOrderNumber = SendVoucherOrder::generateOrderNumber($order->id);
            $order->update(['order_number' => $safeOrderNumber]);

            foreach ($pricedItems as $item) {
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
                    'gross_total' => $item['gross_total'],
                    'global_margin_percentage' => $item['global_margin_percentage'],
                    'global_margin_amount' => $item['global_margin_amount'],
                    'discount_percentage' => $item['discount_percentage'],
                    'discount_amount' => $item['discount_amount'],
                    'total_value' => $item['total_value'],
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
                    'total_deducted' => $item['total_value'],
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

            $this->assertEligiblePrimarySpoc($customer, $spoc);
            if ($customer->status !== 'active') {
                throw new \Exception("Customer is not active (status: {$customer->status}). Only active customers can receive vouchers.");
            }

            $pricingMode = $data['pricing_mode'] ?? 'product';
            $pi = ProformaInvoice::whereKey($data['proforma_invoice_id'])
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $pricing = $this->validateCart($data['items'], $customer->id, $pricingMode, $pi->id);
            if ($pricing['errors']) throw new \Exception(implode(' ', $pricing['errors']));
            $pricedItems = $pricing['items'];
            $productsSubtotal = collect($pricedItems)->sum('total_value');
            $pricingMode = $pricing['pricing_mode'] ?? $pricingMode;
            $invoiceDiscountPercentage = $pricingMode === 'invoice' ? (float) ($pricing['invoice_discount_percentage'] ?? 0) : 0;
            $invoiceDiscountAmount = round($productsSubtotal * $invoiceDiscountPercentage / 100, 2);
            $totalAmount = $productsSubtotal - $invoiceDiscountAmount;
            $totalCodesCount = collect($pricedItems)->sum('quantity');
            $balanceBefore = $customer->balance;

            if (!in_array($pi->status, ['paid', 'partially_delivered'], true) || $pi->availableForDelivery() < $totalAmount) {
                throw new \Exception('Selected Proforma Invoice does not have enough paid available balance for this order.');
            }

            // STRICT: Block negative balance for orders
            if ($balanceBefore < $totalAmount) {
                throw new \Exception("Insufficient customer balance. Available: ₹{$balanceBefore}, Required: ₹{$totalAmount}. Please credit balance via Customers module first.");
            }

            $order = SendVoucherOrder::create([
                'order_number' => 'TEMP-' . uniqid(),
                'customer_id' => $customer->id,
                'spoc_id' => $spoc->id,
                'proforma_invoice_id' => $pi->id,
                'sent_by' => $sentByUserId,
                'total_amount' => $totalAmount,
                'pricing_mode' => $pricingMode,
                'invoice_discount_percentage' => $invoiceDiscountPercentage,
                'invoice_discount_amount' => $invoiceDiscountAmount,
                'products_subtotal' => $productsSubtotal,
                'spoc_name' => $spoc->name,
                'spoc_email' => $spoc->email,
                'spoc_phone' => $spoc->phone,
                'customer_balance_before' => $balanceBefore,
                'customer_balance_after' => $balanceBefore - $totalAmount,
                'status' => 'pending_otp',
                'email_sent_to' => $spoc->email,
                'total_codes_count' => $totalCodesCount,
            ]);

            $safeOrderNumber = SendVoucherOrder::generateOrderNumber($order->id);
            $order->update(['order_number' => $safeOrderNumber]);

            foreach ($pricedItems as $item) {
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
                    'gross_total' => $item['gross_total'],
                    'global_margin_percentage' => $item['global_margin_percentage'],
                    'global_margin_amount' => $item['global_margin_amount'],
                    'discount_percentage' => $item['discount_percentage'],
                    'discount_amount' => $item['discount_amount'],
                    'total_value' => $item['total_value'],
                ]);

                foreach ($codes as $code) {
                    $code->update(['status' => 'reserved', 'order_item_id' => $orderItem->id]);
                }

                CustomerVoucherHistory::create([
                    'customer_id' => $customer->id,
                    'voucher_name' => $product->name,
                    'denomination' => $item['denomination'],
                    'quantity' => $item['quantity'],
                    'total_deducted' => $item['total_value'],
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
     * Recipients: naveentitare52@gmail.com + ptitare@gmail.com
     */
    public function sendOrderOtpEmail(int $orderId): void
    {
        $order = SendVoucherOrder::with(['items.product', 'customer', 'spoc', 'taxInvoice.items'])->findOrFail($orderId);
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
                'gross_total' => (float) ($item->gross_total ?? ($item->denomination * $item->quantity)),
                'global_margin_percentage' => (float) ($item->global_margin_percentage ?? 0),
                'global_margin_amount' => (float) ($item->global_margin_amount ?? 0),
                'discount_percentage' => (float) ($item->discount_percentage ?? 0),
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'total' => $item->total_value,
            ];
        }

        $recipients = $this->billing->approverEmails('order_otp');

        // Send OTP email to all recipients in a single message
        $draftInvoicePdf = $order->tax_invoice_id ? $this->billing->renderDocumentPdf('tax_invoice', $order->tax_invoice_id) : null;
        Mail::to($recipients)->send(new OrderOtpMail($order, $order->customer, $order->spoc, $otp, $itemsSummary, $draftInvoicePdf));

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
    public function verifyOrderOtp(string $orderNumber, string $otp, int $verifiedBy): SendVoucherOrder
{
    $order = $this->findOrderForAction($orderNumber, ['items.product', 'customer', 'spoc', 'proformaInvoice', 'taxInvoice']);

    if ($order->status !== 'pending_otp') {
        throw new \Exception("Order is not awaiting OTP verification. Current status: {$order->status}");
    }

    $customer = $order->customer;

    if (!$customer->verifyOrderOtp($otp, $verifiedBy)) {
        throw new \Exception('Invalid or expired OTP.');
    }

    try {
        DB::transaction(function () use ($order, $verifiedBy) {
            $order->loadMissing(['proformaInvoice', 'taxInvoice', 'customer']);

            if ($order->proformaInvoice) {
                $pi = ProformaInvoice::whereKey($order->proformaInvoice->id)->lockForUpdate()->first();
                $pi->increment('delivered_amount', (float) $order->total_amount);
                $pi->refresh();
                $remaining = $pi->availableForDelivery();
                $isFullyDelivered = round($remaining, 2) <= 0.0 && $this->isProformaFullyDeliveredByItems($pi);
                $pi->update(['status' => $isFullyDelivered ? 'delivered' : 'partially_delivered']);

                if ($isFullyDelivered) {
                    $invoice = $order->taxInvoice ?: $this->billing->createDraftTaxInvoiceForCompletedProformaOrder($order->fresh(['customer']), $pi, $verifiedBy);
                    if ($invoice->status === 'draft') {
                        $invoice = $this->billing->finalizeTaxInvoice($invoice, $verifiedBy);
                    }
                    $order->update(['tax_invoice_id' => $invoice->id]);
                }
            } elseif ($order->taxInvoice && $order->taxInvoice->status === 'draft') {
                $invoice = $this->billing->finalizeTaxInvoice($order->taxInvoice, $verifiedBy);
                $order->update(['tax_invoice_id' => $invoice->id]);
            }
        });

        $this->sendOrderEmail($order->id);
        return $order->fresh()->load(['items.product', 'customer', 'spoc', 'sentBy']);
    } catch (\Exception $e) {
        Log::error("SendVoucher Email Failed after OTP Order {$order->order_number}: " . $e->getMessage(), ['order_id' => $order->id]);
        throw new \Exception("OTP verified but voucher email failed: " . $e->getMessage() . ". Please retry sending vouchers.");
    }
}

    private function isProformaFullyDeliveredByItems(ProformaInvoice $pi): bool
    {
        $pi->loadMissing('items');

        foreach ($pi->items as $item) {
            $delivered = $this->lockedOrDeliveredPiQuantity($pi->id, (int) $item->product_id, (float) $item->denomination);
            if ($delivered < (int) $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resend OTP for an order
     */
    public function resendOrderOtp(string $orderNumber): SendVoucherOrder
{
    $order = $this->findOrderForAction($orderNumber);

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
        $order = $this->findOrderForAction($orderNumber);

        // A repeated cancel is safe: the UI can clear a stale pending-order banner.
        if ($order->status === 'cancelled') {
            return $order;
        }
        
        if (!in_array($order->status, ['pending_otp', 'processing'])) {
            throw new \Exception("Can only cancel orders in pending_otp or processing status.");
        }

        DB::transaction(function () use ($order) {
            // Release reserved codes
            $itemIds = $order->items()->pluck('id');
            SendVoucherCode::whereIn('order_item_id', $itemIds)->where('status','reserved')->update(['status'=>'available','order_item_id'=>null]);

            if ($order->tax_invoice_id) {
                TaxInvoice::whereKey($order->tax_invoice_id)
                    ->where('status', 'draft')
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => $order->sent_by,
                    ]);
            }

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
        $order = SendVoucherOrder::with(['items.product', 'items.codes', 'customer', 'spoc', 'taxInvoice.items'])->findOrFail($orderId);

        if ($order->status === 'cancelled') {
            throw new \Exception("Cannot send delivery access for a cancelled order.");
        }

        $recipientEmail = $this->deliveryRecipientEmail($order);
        if (empty($recipientEmail)) {
            throw new \Exception('Saved SPOC email is missing for this order.');
        }

        $secretKey = $this->generateDeliverySecretKey();
        $deliveryUrl = rtrim(config('avirqo_auth.frontend_url'), '/') . '/download-vouchers/' . urlencode($order->order_number);
        $expiresAt = now()->addDays(15);

        try {
            $finalInvoicePdf = $order->tax_invoice_id ? $this->billing->renderDocumentPdf('tax_invoice', $order->tax_invoice_id) : null;
            Mail::to($recipientEmail)->send(new OrderDeliverySecretMail(
                $order,
                $order->customer,
                $order->spoc,
                $secretKey,
                $deliveryUrl,
                $expiresAt,
                $finalInvoicePdf
            ));

            $order->update([
                'status' => 'sent',
                'sent_at' => now(),
                'email_sent_to' => $recipientEmail,
                'email_attempts' => DB::raw('email_attempts + 1'),
                'failure_reason' => null,
                'delivery_secret_hash' => Hash::make($secretKey),
                'delivery_secret_expires_at' => $expiresAt,
                'delivery_secret_used_at' => null,
                'delivery_secret_sent_at' => now(),
                'delivery_secret_sent_to' => $recipientEmail,
                'delivery_otp_hash' => null,
                'delivery_otp_expires_at' => null,
                'delivery_otp_sent_at' => null,
                'delivery_otp_sent_to' => null,
                'delivery_otp_verified_at' => null,
                'delivery_downloaded_at' => null,
            ]);

            Log::info("Delivery secret sent for {$order->order_number} to {$recipientEmail}");
        } finally {
            unset($secretKey);
        }
    }

    public function requestDeliveryOtp(string $orderNumber, string $email, string $secretKey): array
    {
        $order = $this->findOrderForAction($orderNumber, ['customer', 'spoc']);
        $this->assertDeliveryAccessCanBeRequested($order, $email, $secretKey);

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(5);
        $recipientEmail = $this->deliveryRecipientEmail($order);
        $deliveryUrl = rtrim(config('avirqo_auth.frontend_url'), '/') . '/download-vouchers/' . urlencode($order->order_number);

        Mail::to($recipientEmail)->send(new OrderDeliveryOtpMail(
            $order,
            $order->customer,
            $order->spoc,
            $otp,
            $expiresAt,
            $deliveryUrl
        ));

        $order->update([
            'delivery_otp_hash' => Hash::make($otp),
            'delivery_otp_expires_at' => $expiresAt,
            'delivery_otp_sent_at' => now(),
            'delivery_otp_sent_to' => $recipientEmail,
            'delivery_otp_verified_at' => null,
            'email_attempts' => DB::raw('email_attempts + 1'),
        ]);

        return [
            'message' => 'OTP sent to the SPOC email on file. Please verify within 5 minutes to download the Excel.',
            'expires_at' => $expiresAt->toIso8601String(),
            'recipient_email' => $recipientEmail,
            'order_number' => $order->order_number,
        ];
    }

    public function verifyDeliveryOtp(string $orderNumber, string $email, string $secretKey, string $otp): array
    {
        $order = $this->findOrderForAction($orderNumber, ['customer', 'spoc']);
        $this->assertDeliveryAccessCanBeRequested($order, $email, $secretKey);

        if (empty($order->delivery_otp_hash) || empty($order->delivery_otp_expires_at)) {
            throw new \Exception('OTP has not been requested yet. Please request a new OTP first.');
        }

        if (now()->greaterThan(Carbon::parse($order->delivery_otp_expires_at))) {
            throw new \Exception('OTP expired. Please request a new OTP.');
        }

        if (!Hash::check($otp, $order->delivery_otp_hash)) {
            throw new \Exception('Invalid OTP.');
        }

        $order->update([
            'delivery_otp_verified_at' => now(),
        ]);

        return [
            'message' => 'OTP verified successfully. You can now download the Excel file.',
            'order_number' => $order->order_number,
        ];
    }

    public function downloadDeliveryExcel(string $orderNumber, string $email, string $secretKey, string $otp): array
    {
        $order = $this->findOrderForAction($orderNumber, ['customer', 'spoc', 'items.product']);
        $this->assertDeliveryAccessCanBeRequested($order, $email, $secretKey);

        if (empty($order->delivery_otp_hash) || empty($order->delivery_otp_expires_at)) {
            throw new \Exception('OTP has not been verified yet. Please request and verify the OTP first.');
        }

        if (now()->greaterThan(Carbon::parse($order->delivery_otp_expires_at))) {
            throw new \Exception('OTP expired. Please request a new OTP.');
        }

        if (!Hash::check($otp, $order->delivery_otp_hash)) {
            throw new \Exception('Invalid OTP.');
        }

        $codes = $this->buildDeliveryCodesForOrder($order);
        if (empty($codes)) {
            throw new \Exception("No voucher codes found for order {$order->order_number}");
        }

        $excelContent = $this->buildExcelInMemory($codes, $order->order_number);
        $filename = "Avirqo-Send-Vouchers-{$order->order_number}.xlsx";

        DB::transaction(function () use ($order) {
            $itemIds = $order->items()->pluck('id')->all();
            SendVoucherCode::whereIn('order_item_id', $itemIds)
                ->whereIn('status', ['reserved', 'sent'])
                ->update(['status' => 'used']);

            $order->update([
                'delivery_secret_used_at' => now(),
                'delivery_downloaded_at' => now(),
                'delivery_otp_verified_at' => now(),
            ]);
        });

        return [
            'filename' => $filename,
            'content' => $excelContent,
        ];
    }

    private function generateDeliverySecretKey(): string
    {
        return strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
    }

    private function deliveryRecipientEmail(SendVoucherOrder $order): ?string
    {
        $email = trim((string) ($order->spoc_email ?: $order->spoc?->email));
        return $email !== '' ? $email : null;
    }

    private function assertDeliveryAccessCanBeRequested(SendVoucherOrder $order, string $email, string $secretKey): void
    {
        if (!in_array($order->status, ['sent', 'success'], true)) {
            throw new \Exception('Delivery is only available for sent orders.');
        }

        $recipientEmail = $this->deliveryRecipientEmail($order);
        if (empty($recipientEmail)) {
            throw new \Exception('Saved SPOC email is missing for this order.');
        }

        if (strtolower(trim($email)) !== strtolower($recipientEmail)) {
            throw new \Exception('The email does not match the SPOC email saved on this order.');
        }

        if (empty($order->delivery_secret_hash) || empty($order->delivery_secret_expires_at)) {
            throw new \Exception('Secret key has expired or is not available. Please request a new secret key from order history.');
        }

        if ($order->delivery_secret_used_at) {
            throw new \Exception('This secret key has already been used. Please request a new secret key from order history.');
        }

        if (now()->greaterThan(Carbon::parse($order->delivery_secret_expires_at))) {
            throw new \Exception('Secret key expired. Please request a new secret key from order history.');
        }

        if (!Hash::check($secretKey, $order->delivery_secret_hash)) {
            throw new \Exception('Invalid secret key.');
        }
    }

    private function buildDeliveryCodesForOrder(SendVoucherOrder $order): array
    {
        $allCodes = [];

        foreach ($order->items as $item) {
            $codes = SendVoucherCode::where('order_item_id', $item->id)
                ->whereIn('status', ['reserved', 'sent', 'used'])
                ->get();

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
            }
        }

        return $allCodes;
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

            if ($order->tax_invoice_id) {
                TaxInvoice::whereKey($order->tax_invoice_id)
                    ->where('status', 'draft')
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => $order->sent_by,
                    ]);
            }

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

        $headers = ['Brand Name','Product','Denomination','Currency','Voucher Code','PIN','Expiry Date','Avirqo ID (for verification)'];
        foreach ($headers as $col => $h) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col+1).'1';
            $sheet->setCellValue($cell, $h);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D9E75');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        // Initialize Hashids with a secure salt and minimum length of 7 characters
        $hashids = new Hashids(env('HASHIDS_SALT', 'avirqo_fallback_salt_key'), 7);

        foreach ($codes as $r => $c) {
            $row = $r+2;
            $sheet->setCellValue("A{$row}", $c['brand']);
            $sheet->setCellValue("B{$row}", $c['product_name']);
            $sheet->setCellValue("C{$row}", $c['denomination']);
            $sheet->setCellValue("D{$row}", $c['currency_code']);
            $sheet->setCellValue("E{$row}", $c['code']);
            $sheet->setCellValue("F{$row}", $c['pin'] ?? '');
            $sheet->setCellValue("G{$row}", $c['expiry_date']);
            
            // Encode code_id to a 7-character hashed ID
            $hashedCodeId = isset($c['code_id']) ? $hashids->encode($c['code_id']) : '';
            $sheet->setCellValue("H{$row}", $hashedCodeId);
            
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('@');
        }

        foreach (range('A','H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        // Make the Avirqo ID column (H) visible by default
        $sheet->getColumnDimension('H')->setVisible(true);

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
            ['Instructions:', ''],
            ['1. Open https://app.avirqo.in/verify and provide Avirqo ID to verify the voucher code.', ''],
            ['2. For each voucher, you will find the Avirqo ID in the Last column of the Vouchers sheet.', ''],
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

        if (!in_array($order->status, ['sent', 'success'], true)) {
            throw new \Exception('Can only resend delivery access for sent orders.');
        }

        $this->sendOrderEmail($order->id);
        return $order->fresh(['customer', 'spoc', 'sentBy', 'items.product']);
    }

    public function initiateOrderSpocSwitch(string $orderNumber, int $spocId, int $requestedBy): array
    {
        $order = SendVoucherOrder::with(['customer.spocs', 'spoc'])->where('order_number', $orderNumber)->firstOrFail();

        if (!in_array($order->status, ['pending_otp', 'processing'], true)) {
            throw new \Exception('SPOC can only be switched for pending approval orders.');
        }

        $customer = $order->customer;
        $newSpoc = CustomerSpoc::findOrFail($spocId);
        $this->assertEligiblePrimarySpoc($customer, $newSpoc);

        if ((int) $newSpoc->id === (int) $order->spoc_id) {
            throw new \Exception('Selected SPOC is already assigned to this order.');
        }

        $requestId = (string) Str::uuid();
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $recipients = $this->billing->approverEmails('order_otp');

        $payload = [
            'request_id' => $requestId,
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'user_id' => $requestedBy,
            'otp' => $otp,
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $recipients,
            'old_spoc_name' => $order->spoc_name ?: $order->spoc?->name,
            'old_spoc_email' => $order->spoc_email ?: $order->spoc?->email,
            'new_spoc_id' => $newSpoc->id,
            'new_spoc_name' => $newSpoc->name,
            'new_spoc_email' => $newSpoc->email,
            'customer_name' => $customer->company_name,
        ];

        Cache::put($this->orderSpocSwitchCacheKey($requestedBy, $requestId), $payload, $expiresAt);

        Mail::to($recipients)->send(new OrderSpocSwitchOtpMail(
            $order,
            $customer,
            $order->spoc,
            $newSpoc,
            $otp,
            $expiresAt
        ));

        return [
            'request_id' => $requestId,
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $recipients,
            'message' => 'SPOC switch approval OTP sent to admins.',
        ];
    }

    public function verifyOrderSpocSwitch(string $orderNumber, string $requestId, string $otp, int $verifiedBy): SendVoucherOrder
    {
        $cacheKey = $this->orderSpocSwitchCacheKey($verifiedBy, $requestId);
        $pending = Cache::get($cacheKey);

        if (! $pending || data_get($pending, 'order_number') !== $orderNumber) {
            throw new \Exception('SPOC switch request expired or not found.');
        }

        if (($pending['otp'] ?? null) !== $otp) {
            throw new \Exception('Invalid OTP.');
        }

        $expiresAt = data_get($pending, 'expires_at');
        if ($expiresAt && now()->greaterThan(Carbon::parse($expiresAt))) {
            Cache::forget($cacheKey);
            throw new \Exception('OTP expired. Please request a new SPOC switch.');
        }

        $order = SendVoucherOrder::findOrFail($pending['order_id']);
        $newSpoc = CustomerSpoc::findOrFail($pending['new_spoc_id']);

        $order->update([
            'spoc_id' => $newSpoc->id,
            'spoc_name' => $newSpoc->name,
            'spoc_email' => $newSpoc->email,
            'spoc_phone' => $newSpoc->phone,
            'email_sent_to' => $newSpoc->email,
        ]);

        Cache::forget($cacheKey);

        return $order->fresh()->load(['customer.spocs', 'spoc', 'sentBy', 'items.product']);
    }


    public function dispatchAsync(int $orderId): void
    {
        SendVoucherEmailJob::dispatch($orderId)->onQueue('send-vouchers');
    }

    private function orderSpocSwitchCacheKey(int $userId, string $requestId): string
    {
        return "send-vouchers:order-spoc-switch:{$userId}:{$requestId}";
    }

    private function assertEligiblePrimarySpoc(Customer $customer, CustomerSpoc $spoc): void
    {
        if ((int) $spoc->customer_id !== (int) $customer->id) {
            throw new \Exception('Selected SPOC does not belong to selected customer.');
        }

        if (empty($spoc->email) || ! filter_var($spoc->email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('SPOC email invalid or missing: ' . ($spoc->email ?? 'empty'));
        }

        if ($spoc->status !== 'active') {
            throw new \Exception("Selected SPOC is not active (status: {$spoc->status}). Only active SPOCs can receive vouchers.");
        }

        if (! $spoc->is_primary) {
            throw new \Exception('Selected SPOC must be the primary SPOC.');
        }

        $activePrimaryCount = $customer->spocs()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->count();

        if ($activePrimaryCount !== 1) {
            throw new \Exception("Customer must have exactly one active primary SPOC. Found {$activePrimaryCount}.");
        }
    }

    private function findOrderForAction(string|int $identifier, array $with = []): SendVoucherOrder
    {
        $query = SendVoucherOrder::query();

        if (!empty($with)) {
            $query->with($with);
        }

        if (is_numeric($identifier)) {
            $order = $query->find((int) $identifier);
            if ($order) {
                return $order;
            }
        }

        $order = $query->where('order_number', (string) $identifier)->first();

        if (! $order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('No query results for model [App\\Models\\SendVoucherOrder].');
        }

        return $order;
    }
}
