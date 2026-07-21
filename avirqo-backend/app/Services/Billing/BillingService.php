<?php

namespace App\Services\Billing;

use App\Mail\BillingInternalDocumentMail;
use App\Models\BillingCreditNoteApplication;
use App\Models\BillingCreditDebitNote;
use App\Models\BillingDocumentEmail;
use App\Models\BillingNumberSequence;
use App\Models\BillingOtpApprover;
use App\Models\BillingPayment;
use App\Models\Customer;
use App\Models\CustomerBalanceLog;
use App\Models\ProformaInvoice;
use App\Models\SendVoucherCode;
use App\Models\SendVoucherOrder;
use App\Models\SendVoucherProduct;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceItem;
use App\Models\VoucherCampaignProduct;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function approverEmails(string $groupKey): array
    {
        $group = BillingOtpApprover::where('group_key', $groupKey)->where('is_active', true)->first();
        $emails = $group?->emails ?: ['naveentitare52@gmail.com', 'ptitare@gmail.com'];
        return array_values(array_unique(array_filter($emails)));
    }

    public function financialYear(?\DateTimeInterface $date = null): int
    {
        $date = $date ? now()->parse($date->format('Y-m-d')) : now();
        return (int) ($date->month >= 4 ? $date->copy()->addYear()->format('y') : $date->format('y'));
    }

    public function nextOfficialNumber(string $type, ?\DateTimeInterface $date = null): string
    {
        $fy = $this->financialYear($date);
        $prefix = match ($type) {
            'tax_invoice', 'proforma_invoice' => 'AVQ/INV',
            'credit_note' => 'AVQ/CN',
            'debit_note' => 'AVQ/DN',
            'purchase_order' => 'AVQ/PO',
            default => throw new \InvalidArgumentException("Unsupported document type {$type}"),
        };

        $sequenceType = in_array($type, ['tax_invoice', 'proforma_invoice'], true) ? 'invoice' : $type;

        return DB::transaction(function () use ($sequenceType, $fy, $prefix) {
            $sequence = BillingNumberSequence::where('document_type', $sequenceType)
                ->where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = BillingNumberSequence::create([
                    'document_type' => $sequenceType,
                    'financial_year' => $fy,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');
            return sprintf('%s/%02d/%04d', $prefix, $fy, $sequence->last_number);
        });
    }

    public function draftNumber(string $prefix): string
    {
        return sprintf('%s-DRAFT-%s-%s', strtoupper($prefix), now()->format('Ymd'), strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
    }

    public function listProformas(array $filters = [])
    {
        return ProformaInvoice::with(['customer', 'items.product', 'creditNoteApplications.creditNote'])
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                $qq->where('pi_number', 'like', "%{$s}%")
                    ->orWhere('draft_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('company_name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20);
    }

    public function storeProforma(array $data, int $userId): ProformaInvoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['items'] = $this->normalizeDocumentItems($data['items']);
            $this->assertProductsAvailableForCustomer((int) $data['customer_id'], $data['items']);

            $invoice = ProformaInvoice::create([
                'draft_number' => $this->draftNumber('PI'),
                'customer_id' => $data['customer_id'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
                'discount_type' => $data['discount_type'] ?? 'campaign',
                'invoice_discount_percentage' => (float) ($data['invoice_discount_percentage'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->replaceProformaItems($invoice, $data['items']);
            $this->syncCreditNoteReservations($invoice, $data['credit_note_applications'] ?? [], $userId);
            return $invoice->fresh(['customer', 'items.product', 'creditNoteApplications.creditNote']);
        });
    }

    public function updateProforma(ProformaInvoice $invoice, array $data, int $userId): ProformaInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new \Exception('This Proforma Invoice cannot be edited.');
        }

        return DB::transaction(function () use ($invoice, $data, $userId) {
            if (isset($data['items'])) {
                $data['items'] = $this->normalizeDocumentItems($data['items']);
                $this->assertProductsAvailableForCustomer((int) ($data['customer_id'] ?? $invoice->customer_id), $data['items']);
            }

            $invoice->update([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'valid_until' => array_key_exists('valid_until', $data) ? $data['valid_until'] : $invoice->valid_until,
                'discount_type' => $data['discount_type'] ?? $invoice->discount_type,
                'invoice_discount_percentage' => array_key_exists('invoice_discount_percentage', $data) ? (float) $data['invoice_discount_percentage'] : $invoice->invoice_discount_percentage,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'updated_by' => $userId,
            ]);

            if (isset($data['items'])) {
                $this->replaceProformaItems($invoice, $data['items']);
            }

            if (array_key_exists('credit_note_applications', $data)) {
                $this->syncCreditNoteReservations($invoice, $data['credit_note_applications'] ?? [], $userId);
            }

            return $invoice->fresh(['customer', 'items.product', 'creditNoteApplications.creditNote']);
        });
    }

    public function finalizeProforma(ProformaInvoice $invoice, int $userId): ProformaInvoice
    {
        if ($invoice->status !== 'draft') {
            return $invoice->fresh(['customer', 'items.product', 'creditNoteApplications.creditNote']);
        }

        DB::transaction(function () use ($invoice, $userId) {
            $invoice = ProformaInvoice::lockForUpdate()->findOrFail($invoice->id);
            $invoice->update([
                'pi_number' => $this->nextOfficialNumber('proforma_invoice'),
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            $this->applyReservedCreditNotes($invoice, $userId);
        });

        return $invoice->fresh(['customer', 'items.product', 'creditNoteApplications.creditNote']);
    }

    public function cancelProforma(ProformaInvoice $invoice, int $userId): ProformaInvoice
    {
        if ((float) $invoice->delivered_amount > 0) {
            throw new \Exception('PI has delivered vouchers. Update/reconcile the PI instead of cancelling it.');
        }

        return DB::transaction(function () use ($invoice, $userId) {
            $this->cancelReservedCreditNotes($invoice);
            $balanceToReverse = (float) $invoice->balance_added_amount;
            if ($balanceToReverse > 0) {
                $customer = $invoice->customer()->lockForUpdate()->first();
                $customer->decrement('balance', $balanceToReverse);
                CustomerBalanceLog::create([
                    'customer_id' => $customer->id,
                    'type' => 'debit',
                    'amount' => $balanceToReverse,
                    'balance_after' => $customer->fresh()->balance,
                    'note' => "PI {$invoice->pi_number} cancelled - balance reversed",
                    'done_by' => $userId,
                ]);
            }

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
            ]);

            return $invoice->fresh(['customer', 'items.product']);
        });
    }

    public function capturePayment(array $data, int $userId, mixed $attachment = null): BillingPayment
    {
        return DB::transaction(function () use ($data, $userId, $attachment) {
            $pi = ProformaInvoice::with('customer')->lockForUpdate()->findOrFail($data['proforma_invoice_id']);
            if (! in_array($pi->status, ['finalized', 'paid', 'partially_delivered'], true)) {
                throw new \Exception('Payment can be captured only against a finalized PI.');
            }

            $amount = round((float) $data['amount'], 2);
            $unpaid = max(0, (float) $pi->total_amount - (float) $pi->paid_amount);
            if ($unpaid <= 0) {
                throw new \Exception('This PI is already fully paid. No remaining payment is due.');
            }
            $balanceAdded = min($amount, $unpaid);
            $creditNoteAmount = max(0, $amount - $balanceAdded);
            $attachmentPath = $attachment ? $attachment->store('billing-payment-proofs', 'public') : null;

            $payment = BillingPayment::create([
                'payment_number' => 'PAY-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
                'customer_id' => $pi->customer_id,
                'proforma_invoice_id' => $pi->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'balance_added_amount' => $balanceAdded,
                'credit_note_amount' => $creditNoteAmount,
                'mode' => $data['mode'],
                'reference_no' => $data['reference_no'] ?? null,
                'details' => $data['details'],
                'attachment_path' => $attachmentPath,
                'created_by' => $userId,
            ]);

            if ($balanceAdded > 0) {
                $customer = Customer::lockForUpdate()->findOrFail($pi->customer_id);
                $customer->increment('balance', $balanceAdded);
                CustomerBalanceLog::create([
                    'customer_id' => $customer->id,
                    'type' => 'credit',
                    'amount' => $balanceAdded,
                    'balance_after' => $customer->fresh()->balance,
                    'note' => "Payment captured against PI {$pi->pi_number}",
                    'done_by' => $userId,
                ]);
            }

            if ($creditNoteAmount > 0) {
                BillingCreditDebitNote::create([
                    'draft_number' => $this->draftNumber('CN'),
                    'note_number' => $this->nextOfficialNumber('credit_note'),
                    'type' => 'credit',
                    'customer_id' => $pi->customer_id,
                    'proforma_invoice_id' => $pi->id,
                    'payment_id' => $payment->id,
                    'status' => 'active',
                    'amount' => $creditNoteAmount,
                    'remaining_amount' => $creditNoteAmount,
                    'reason' => 'Excess payment received against PI',
                    'finalized_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            $pi->increment('paid_amount', $amount);
            $pi->increment('balance_added_amount', $balanceAdded);
            $pi->refresh();
            $pi->update(['status' => $pi->availableForDelivery() > 0 ? 'paid' : $pi->status]);

            return $payment->fresh(['customer', 'proformaInvoice']);
        });
    }

    public function invalidatePayment(BillingPayment $payment, string $reason, int $userId): BillingPayment
    {
        if ($payment->status === 'invalid') {
            return $payment;
        }

        $pi = $payment->proformaInvoice()->lockForUpdate()->first();
        if ($pi && (float) $pi->delivered_amount > 0) {
            throw ValidationException::withMessages([
                'payment' => 'Payment cannot be marked invalid because PI balance has already been used for voucher delivery.',
            ]);
        }

        $hasAppliedCreditNote = BillingCreditDebitNote::where('payment_id', $payment->id)
            ->whereHas('applications', fn ($q) => $q->whereIn('status', ['reserved', 'applied']))
            ->exists();

        if ($hasAppliedCreditNote) {
            throw ValidationException::withMessages([
                'payment' => 'Payment cannot be marked invalid because its credit note has already been reserved or applied to another PI.',
            ]);
        }

        return DB::transaction(function () use ($payment, $pi, $reason, $userId) {
            $balanceAdded = (float) $payment->balance_added_amount;
            if ($balanceAdded > 0) {
                $customer = Customer::lockForUpdate()->findOrFail($payment->customer_id);
                $customer->decrement('balance', $balanceAdded);
                CustomerBalanceLog::create([
                    'customer_id' => $customer->id,
                    'type' => 'debit',
                    'amount' => $balanceAdded,
                    'balance_after' => $customer->fresh()->balance,
                    'note' => "Payment {$payment->payment_number} marked invalid",
                    'done_by' => $userId,
                ]);
            }

            if ($pi) {
                $pi->decrement('paid_amount', (float) $payment->amount);
                $pi->decrement('balance_added_amount', $balanceAdded);
                $pi->refresh();
                $pi->update(['status' => (float) $pi->paid_amount > 0 ? 'paid' : 'finalized']);
            }

            BillingCreditDebitNote::where('payment_id', $payment->id)->update(['status' => 'cancelled', 'remaining_amount' => 0]);

            $payment->update([
                'status' => 'invalid',
                'invalidated_at' => now(),
                'invalidated_by' => $userId,
                'invalid_reason' => $reason,
            ]);

            return $payment->fresh(['customer', 'proformaInvoice']);
        });
    }

    public function applyCreditNoteToPiBalance(BillingCreditDebitNote $note, ProformaInvoice $targetPi, float $requestedAmount, int $userId): BillingCreditDebitNote
    {
        if ($note->type !== 'credit' || ! in_array($note->status, ['active'], true) || (float) $note->remaining_amount <= 0) {
            throw new \Exception('Only active credit notes with remaining amount can be applied.');
        }

        return DB::transaction(function () use ($note, $targetPi, $requestedAmount, $userId) {
            $note = BillingCreditDebitNote::lockForUpdate()->findOrFail($note->id);
            $pi = ProformaInvoice::lockForUpdate()->findOrFail($targetPi->id);

            if ((int) $note->customer_id !== (int) $pi->customer_id) {
                throw new \Exception('Credit note can only be applied to a PI of the same customer.');
            }

            if (in_array($pi->status, ['draft', 'cancelled', 'delivered'], true)) {
                throw new \Exception('Credit note can only be applied to a finalized PI with pending payment.');
            }

            $sameSourcePi = (int) $note->proforma_invoice_id === (int) $pi->id;
            $balanceGap = round(max(0, (float) $pi->paid_amount - (float) $pi->balance_added_amount), 2);
            $pendingPayment = round(max(0, (float) $pi->total_amount - (float) $pi->paid_amount), 2);
            $maxApplicable = $sameSourcePi && $balanceGap > 0 ? $balanceGap : $pendingPayment;
            if ($maxApplicable <= 0) {
                throw new \Exception('This PI has no pending payment to apply credit note against.');
            }

            $amountToApply = round(min((float) $note->remaining_amount, $maxApplicable, $requestedAmount), 2);
            if ($amountToApply <= 0) {
                throw new \Exception('No credit note amount is available to apply.');
            }

            $customer = Customer::lockForUpdate()->findOrFail($pi->customer_id);
            $customer->increment('balance', $amountToApply);
            $customer->refresh();

            if (! $sameSourcePi || $balanceGap <= 0) {
                $pi->increment('paid_amount', $amountToApply);
            }
            $pi->increment('balance_added_amount', $amountToApply);
            $pi->refresh();
            $pi->update(['status' => $pi->availableForDelivery() > 0 ? 'paid' : $pi->status]);

            $remaining = round((float) $note->remaining_amount - $amountToApply, 2);
            $note->update([
                'remaining_amount' => max(0, $remaining),
                'status' => $remaining <= 0 ? 'applied' : $note->status,
            ]);

            BillingCreditNoteApplication::create([
                'billing_credit_debit_note_id' => $note->id,
                'proforma_invoice_id' => $pi->id,
                'customer_id' => $pi->customer_id,
                'amount' => $amountToApply,
                'status' => 'applied',
                'reserved_by' => $userId,
                'reserved_at' => now(),
                'applied_at' => now(),
            ]);

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $amountToApply,
                'balance_after' => $customer->balance,
                'note' => "Credit Note {$note->note_number} applied to PI {$pi->pi_number}",
                'done_by' => $userId,
            ]);

            return $note->fresh(['customer', 'proformaInvoice', 'payment']);
        });
    }

    public function pendingProformasForCreditNote(BillingCreditDebitNote $note)
    {
        if ($note->type !== 'credit' || $note->status !== 'active' || (float) $note->remaining_amount <= 0) {
            return collect();
        }

        return ProformaInvoice::where('customer_id', $note->customer_id)
            ->whereNotIn('status', ['draft', 'cancelled', 'delivered'])
            ->latest()
            ->get()
            ->map(function (ProformaInvoice $pi) use ($note) {
                $sameSourcePi = (int) $note->proforma_invoice_id === (int) $pi->id;
                $balanceGap = round(max(0, (float) $pi->paid_amount - (float) $pi->balance_added_amount), 2);
                $pendingPayment = round(max(0, (float) $pi->total_amount - (float) $pi->paid_amount), 2);
                $applicableAmount = $sameSourcePi && $balanceGap > 0 ? $balanceGap : $pendingPayment;

                return [
                    'id' => $pi->id,
                    'pi_number' => $pi->pi_number ?: $pi->draft_number,
                    'status' => $pi->status,
                    'total_amount' => (float) $pi->total_amount,
                    'paid_amount' => (float) $pi->paid_amount,
                    'balance_added_amount' => (float) $pi->balance_added_amount,
                    'delivered_amount' => (float) $pi->delivered_amount,
                    'pending_payment' => $pendingPayment,
                    'balance_gap' => $balanceGap,
                    'applicable_amount' => round($applicableAmount, 2),
                ];
            })
            ->filter(fn ($pi) => $pi['applicable_amount'] > 0)
            ->values();
    }

    public function availableCreditNotesForCustomer(int $customerId, ?int $proformaInvoiceId = null)
    {
        return BillingCreditDebitNote::with(['proformaInvoice'])
            ->where('type', 'credit')
            ->where('status', 'active')
            ->where('customer_id', $customerId)
            ->where('remaining_amount', '>', 0)
            ->get()
            ->map(function (BillingCreditDebitNote $note) use ($proformaInvoiceId) {
                $reservedElsewhere = BillingCreditNoteApplication::where('billing_credit_debit_note_id', $note->id)
                    ->where('status', 'reserved')
                    ->when($proformaInvoiceId, fn ($q) => $q->where('proforma_invoice_id', '!=', $proformaInvoiceId))
                    ->sum('amount');
                $reservedForThisPi = $proformaInvoiceId
                    ? BillingCreditNoteApplication::where('billing_credit_debit_note_id', $note->id)
                        ->where('proforma_invoice_id', $proformaInvoiceId)
                        ->where('status', 'reserved')
                        ->sum('amount')
                    : 0;
                $available = max(0, (float) $note->remaining_amount - (float) $reservedElsewhere);

                return [
                    'id' => $note->id,
                    'note_number' => $note->note_number,
                    'draft_number' => $note->draft_number,
                    'amount' => (float) $note->amount,
                    'remaining_amount' => (float) $note->remaining_amount,
                    'available_amount' => round($available, 2),
                    'reserved_for_this_pi' => round((float) $reservedForThisPi, 2),
                    'source_pi_number' => $note->proformaInvoice?->pi_number ?: $note->proformaInvoice?->draft_number,
                    'reason' => $note->reason,
                ];
            })
            ->filter(fn ($note) => $note['available_amount'] > 0 || $note['reserved_for_this_pi'] > 0)
            ->values();
    }

    public function paidProformasForCustomer(int $customerId)
    {
        return ProformaInvoice::with('items.product')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['paid', 'partially_delivered'])
            ->get()
            ->filter(fn (ProformaInvoice $pi) => $pi->availableForDelivery() > 0)
            ->values()
            ->map(fn (ProformaInvoice $pi) => [
                'id' => $pi->id,
                'pi_number' => $pi->pi_number,
                'draft_number' => $pi->draft_number,
                'status' => $pi->status,
                'total_amount' => (float) $pi->total_amount,
                'paid_amount' => (float) $pi->paid_amount,
                'delivered_amount' => (float) $pi->delivered_amount,
                'available_amount' => $pi->availableForDelivery(),
                'discount_type' => $pi->discount_type ?? 'campaign',
                'invoice_discount_percentage' => (float) ($pi->invoice_discount_percentage ?? 0),
                'items' => $pi->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'brand' => $item->brand,
                    'product_name' => $item->product_name,
                    'denomination' => (float) $item->denomination,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percentage' => (float) $item->discount_percentage,
                    'currency_code' => $item->currency_code,
                    'product' => $item->product,
                    'delivered_quantity' => $this->deliveredQuantityForPiItem($pi->id, (int) $item->product_id, (float) $item->denomination),
                    'available_stock' => $item->product?->availableCodesCount((float) $item->denomination) ?? 0,
                ])->map(function (array $item) {
                    $item['pending_quantity'] = max(0, (int) $item['quantity'] - (int) $item['delivered_quantity']);
                    return $item;
                })->values(),
            ]);
    }

    private function deliveredQuantityForPiItem(int $piId, int $productId, float $denomination): int
    {
        return (int) DB::table('send_voucher_order_items as items')
            ->join('send_voucher_orders as orders', 'orders.id', '=', 'items.order_id')
            ->where('orders.proforma_invoice_id', $piId)
            ->whereIn('orders.status', ['pending_otp', 'processing', 'sent', 'success'])
            ->where('items.product_id', $productId)
            ->where('items.denomination', $denomination)
            ->sum('items.quantity');
    }

    public function createDraftTaxInvoiceForOrder(SendVoucherOrder $order, ProformaInvoice $pi, array $pricedItems, int $userId): TaxInvoice
    {
        return $this->createDraftTaxInvoiceForCompletedProformaOrder($order, $pi, $userId);
    }

    public function createDraftTaxInvoiceForCompletedProformaOrder(SendVoucherOrder $order, ProformaInvoice $pi, int $userId): TaxInvoice
    {
        return DB::transaction(function () use ($order, $pi, $userId) {
            if ($existing = TaxInvoice::where('proforma_invoice_id', $pi->id)->where('status', '!=', 'cancelled')->first()) {
                $order->update(['tax_invoice_id' => $existing->id]);
                return $existing->fresh(['customer', 'proformaInvoice', 'items']);
            }

            $invoice = TaxInvoice::create([
                'draft_number' => $this->draftNumber('INV'),
                'customer_id' => $order->customer_id,
                'proforma_invoice_id' => $pi->id,
                'send_voucher_order_id' => $order->id,
                'status' => 'draft',
                'invoice_date' => now()->toDateString(),
                'place_of_supply' => $order->customer->location ?? null,
                'discount_type' => $pi->discount_type ?? 'campaign',
                'invoice_discount_percentage' => (float) ($pi->invoice_discount_percentage ?? 0),
                'created_by' => $userId,
            ]);

            $this->replaceTaxInvoiceItems($invoice, $pi->items()->get()->map(fn ($item) => [
                'product_id' => $item->product_id,
                'denomination' => (float) $item->denomination,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_percentage' => (float) $item->discount_percentage,
                'gst_rate' => (float) $item->gst_rate,
                'hsn_sac' => $item->hsn_sac,
            ])->all());
            $order->update(['tax_invoice_id' => $invoice->id]);
            return $invoice->fresh(['customer', 'proformaInvoice', 'items']);
        });
    }

    public function finalizeTaxInvoice(TaxInvoice $invoice, int $userId): TaxInvoice
    {
        if ($invoice->status === 'finalized') {
            return $invoice->fresh(['customer', 'proformaInvoice', 'items']);
        }

        $invoice->update([
            'invoice_number' => $invoice->proformaInvoice?->pi_number ?: $this->nextOfficialNumber('tax_invoice'),
            'status' => 'finalized',
            'invoice_date' => now()->toDateString(),
            'finalized_at' => now(),
            'finalized_by' => $userId,
        ]);

        return $invoice->fresh(['customer', 'proformaInvoice', 'items']);
    }

    public function renderDocumentPdf(string $type, int $id): string
    {
        $document = match ($type) {
            'proforma_invoice' => ProformaInvoice::with(['customer', 'items.product'])->findOrFail($id),
            'tax_invoice' => TaxInvoice::with(['customer', 'proformaInvoice', 'items.product'])->findOrFail($id),
            'credit_note', 'debit_note' => BillingCreditDebitNote::with(['customer', 'proformaInvoice'])->findOrFail($id),
            default => throw new \InvalidArgumentException('Unsupported document type.'),
        };

        $view = match ($type) {
            'proforma_invoice' => 'billing.proforma-invoice',
            'tax_invoice' => 'billing.tax-invoice',
            'credit_note', 'debit_note' => 'billing.credit-debit-note',
        };

        return Pdf::loadView($view, [
            'document' => $document,
            'type' => $type,
            'amountInWords' => $this->amountInWords((float) ($document->total_amount ?? $document->amount ?? 0)),
        ])->setPaper('a4')->output();
    }

    public function emailInternalDocument(string $type, int $id, string $to, ?string $message, int $userId): void
    {
        if (! preg_match('/@(avirqo\\.com|avirqo\\.in)$/i', $to)) {
            throw new \Exception('Internal document emails can only be sent to avirqo.com or avirqo.in addresses.');
        }

        $document = match ($type) {
            'proforma_invoice' => ProformaInvoice::with('customer')->findOrFail($id),
            'tax_invoice' => TaxInvoice::with('customer')->findOrFail($id),
            'credit_note', 'debit_note' => BillingCreditDebitNote::with('customer')->findOrFail($id),
            default => throw new \InvalidArgumentException('Unsupported document type.'),
        };

        $documentNumber = match ($type) {
            'proforma_invoice' => $document->pi_number ?: $document->draft_number,
            'tax_invoice' => $document->invoice_number ?: $document->draft_number,
            'credit_note', 'debit_note' => $document->note_number ?: $document->draft_number,
        };

        $pdf = $this->renderDocumentPdf($type, $id);
        Mail::to($to)->send(new BillingInternalDocumentMail(
            $type,
            $id,
            $pdf,
            $message,
            $documentNumber,
            $document->customer?->company_name,
        ));
        BillingDocumentEmail::create([
            'document_type' => $type,
            'document_id' => $id,
            'to_email' => $to,
            'message' => $message,
            'sent_by' => $userId,
            'sent_at' => now(),
        ]);
    }

    private function replaceProformaItems(ProformaInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        $totals = $this->createDocumentItems($invoice, $items, 'proforma');
        $invoice->update($totals);
    }

    private function replaceTaxInvoiceItems(TaxInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        $totals = $this->createDocumentItems($invoice, $items, 'tax');
        $invoice->update($totals);
    }

    private function syncCreditNoteReservations(ProformaInvoice $invoice, array $applications, int $userId): void
    {
        if ($invoice->status !== 'draft') {
            throw new \Exception('Credit notes can only be reserved on draft Proforma Invoices.');
        }

        BillingCreditNoteApplication::where('proforma_invoice_id', $invoice->id)
            ->where('status', 'reserved')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        $applications = collect($applications)
            ->map(fn ($row) => [
                'credit_note_id' => (int) ($row['credit_note_id'] ?? $row['billing_credit_debit_note_id'] ?? 0),
                'amount' => round((float) ($row['amount'] ?? 0), 2),
            ])
            ->filter(fn ($row) => $row['credit_note_id'] > 0 && $row['amount'] > 0)
            ->groupBy('credit_note_id')
            ->map(fn ($rows, $noteId) => [
                'credit_note_id' => (int) $noteId,
                'amount' => round($rows->sum('amount'), 2),
            ])
            ->values();

        if ($applications->isEmpty()) {
            return;
        }

        $totalReserved = round($applications->sum('amount'), 2);
        if ($totalReserved > (float) $invoice->total_amount) {
            throw new \Exception('Credit note amount cannot be more than the PI total.');
        }

        foreach ($applications as $application) {
            $note = BillingCreditDebitNote::lockForUpdate()->findOrFail($application['credit_note_id']);
            if ($note->type !== 'credit' || $note->status !== 'active' || (int) $note->customer_id !== (int) $invoice->customer_id) {
                throw new \Exception('Only active credit notes for the selected customer can be reserved.');
            }

            $reservedElsewhere = BillingCreditNoteApplication::where('billing_credit_debit_note_id', $note->id)
                ->where('status', 'reserved')
                ->where('proforma_invoice_id', '!=', $invoice->id)
                ->lockForUpdate()
                ->sum('amount');
            $available = round((float) $note->remaining_amount - (float) $reservedElsewhere, 2);
            if ($application['amount'] > $available) {
                throw new \Exception("Credit Note {$note->note_number} has only ₹".number_format($available, 2).' available.');
            }

            BillingCreditNoteApplication::create([
                'billing_credit_debit_note_id' => $note->id,
                'proforma_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $application['amount'],
                'status' => 'reserved',
                'reserved_by' => $userId,
                'reserved_at' => now(),
            ]);
        }
    }

    private function applyReservedCreditNotes(ProformaInvoice $invoice, int $userId): void
    {
        $applications = BillingCreditNoteApplication::where('proforma_invoice_id', $invoice->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get();

        foreach ($applications as $application) {
            $note = BillingCreditDebitNote::lockForUpdate()->findOrFail($application->billing_credit_debit_note_id);
            if ($note->type !== 'credit' || $note->status !== 'active' || (int) $note->customer_id !== (int) $invoice->customer_id) {
                throw new \Exception('Only active credit notes for the selected customer can be applied.');
            }

            $amountToApply = round(min((float) $application->amount, (float) $note->remaining_amount), 2);
            if ($amountToApply <= 0 || $amountToApply < (float) $application->amount) {
                throw new \Exception("Credit Note {$note->note_number} no longer has the reserved amount available.");
            }

            $customer = Customer::lockForUpdate()->findOrFail($invoice->customer_id);
            $customer->increment('balance', $amountToApply);
            $customer->refresh();

            $invoice->increment('paid_amount', $amountToApply);
            $invoice->increment('balance_added_amount', $amountToApply);
            $invoice->refresh();
            $invoice->update(['status' => $invoice->availableForDelivery() > 0 ? 'paid' : $invoice->status]);

            $remaining = round((float) $note->remaining_amount - $amountToApply, 2);
            $note->update([
                'remaining_amount' => max(0, $remaining),
                'status' => $remaining <= 0 ? 'applied' : $note->status,
            ]);

            $application->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);

            CustomerBalanceLog::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $amountToApply,
                'balance_after' => $customer->balance,
                'note' => "Credit Note {$note->note_number} applied while finalizing PI {$invoice->pi_number}",
                'done_by' => $userId,
            ]);
        }
    }

    private function cancelReservedCreditNotes(ProformaInvoice $invoice): void
    {
        BillingCreditNoteApplication::where('proforma_invoice_id', $invoice->id)
            ->where('status', 'reserved')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }

    private function createDocumentItems(ProformaInvoice|TaxInvoice $document, array $items, string $kind): array
    {
        $items = $this->normalizeDocumentItems($items);
        $subtotal = $discount = $taxable = $cgst = $sgst = $igst = $total = 0.0;
        $usesInvoiceDiscount = ($document->discount_type ?? 'campaign') === 'invoice';

        foreach ($items as $item) {
            $product = SendVoucherProduct::findOrFail($item['product_id']);
            $denomination = (float) $item['denomination'];

            $denominationExists = SendVoucherCode::where('product_id', $product->id)
                ->where('denomination', $denomination)
                ->exists();

            if (! $denominationExists) {
                throw new \Exception("Denomination {$denomination} is not available in voucher stock for {$product->brand} — {$product->name}.");
            }

            $qty = (int) $item['quantity'];
            $unit = (float) ($item['unit_price'] ?? $denomination);
            $lineGross = round($unit * $qty, 2);
            $discountPercentage = $usesInvoiceDiscount ? 0.0 : (float) ($item['discount_percentage'] ?? 0);
            $lineDiscount = round($lineGross * $discountPercentage / 100, 2);
            $lineTaxable = round($lineGross - $lineDiscount, 2);
            $gstRate = $kind === 'proforma' ? 0.0 : (float) ($item['gst_rate'] ?? $product->gst_rate ?? 0);
            $lineIgst = round($lineTaxable * $gstRate / 100, 2);
            $lineTotal = round($lineTaxable + $lineIgst, 2);

            $payload = [
                'product_id' => $product->id,
                'brand' => $product->brand,
                'product_name' => $product->name,
                'hsn_sac' => $item['hsn_sac'] ?? $product->hsn_sac ?? null,
                'currency_code' => $product->currency_code ?? 'INR',
                'denomination' => $denomination,
                'quantity' => $qty,
                'unit_price' => $unit,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $lineDiscount,
                'taxable_value' => $lineTaxable,
                'gst_rate' => $gstRate,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => $lineIgst,
                'line_total' => $lineTotal,
            ];

            $kind === 'proforma'
                ? $document->items()->create($payload)
                : $document->items()->create($payload);

            $subtotal += $lineGross;
            $discount += $lineDiscount;
            $taxable += $lineTaxable;
            $igst += $lineIgst;
            $total += $lineTotal;
        }

        if ($usesInvoiceDiscount) {
            $discount = round($subtotal * (float) ($document->invoice_discount_percentage ?? 0) / 100, 2);
            $taxable = round($subtotal - $discount, 2);
            $cgst = $sgst = $igst = 0.0;
            $total = $taxable;
        }

        $rounded = round($total);
        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discount, 2),
            'taxable_value' => round($taxable, 2),
            'cgst_amount' => round($cgst, 2),
            'sgst_amount' => round($sgst, 2),
            'igst_amount' => round($igst, 2),
            'round_off' => round($rounded - $total, 2),
            'total_amount' => round($rounded, 2),
        ];
    }

    private function normalizeDocumentItems(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['denomination'])) {
                continue;
            }

            $key = ((int) $item['product_id']).'|'.number_format((float) $item['denomination'], 2, '.', '');
            if (! isset($merged[$key])) {
                $merged[$key] = $item;
                $merged[$key]['quantity'] = 0;
            }

            $merged[$key]['quantity'] += (int) ($item['quantity'] ?? 0);
        }

        return array_values(array_filter($merged, fn (array $item) => (int) ($item['quantity'] ?? 0) > 0));
    }

    private function assertProductsAvailableForCustomer(int $customerId, array $items): void
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $globalBlacklisted = SendVoucherProduct::whereIn('id', $productIds)
            ->where('is_blacklisted', true)
            ->pluck('name')
            ->values();

        if ($globalBlacklisted->isNotEmpty()) {
            throw new \Exception('These products are globally blacklisted and cannot be used: '.$globalBlacklisted->join(', '));
        }

        $campaignId = DB::table('voucher_campaign_customers as assignments')
            ->join('voucher_campaigns as campaigns', 'campaigns.id', '=', 'assignments.campaign_id')
            ->where('assignments.customer_id', $customerId)
            ->where('campaigns.is_active', true)
            ->value('assignments.campaign_id');

        if (! $campaignId) {
            throw new \Exception('This customer is not assigned to an active voucher campaign.');
        }

        $campaignBlacklisted = VoucherCampaignProduct::query()
            ->join('send_voucher_products as products', 'products.id', '=', 'voucher_campaign_products.product_id')
            ->where('voucher_campaign_products.campaign_id', $campaignId)
            ->whereIn('voucher_campaign_products.product_id', $productIds)
            ->where('voucher_campaign_products.is_blacklisted', true)
            ->pluck('products.name')
            ->values();

        if ($campaignBlacklisted->isNotEmpty()) {
            throw new \Exception('These products are blacklisted for this customer campaign and cannot be used: '.$campaignBlacklisted->join(', '));
        }
    }

    private function amountInWords(float $amount): string
    {
        $whole = (int) round($amount);
        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
            return 'INR ' . ucfirst($fmt->format($whole)) . ' only';
        }
        return 'INR ' . number_format($whole, 0, '.', ',') . ' only';
    }
}
