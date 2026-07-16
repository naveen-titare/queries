<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerBalanceLog;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function list(array $filters = [])
    {
        return Customer::with('spocs')
            ->when($filters['search'] ?? null, fn($q, $s) =>
                $q->where('company_name', 'like', "%{$s}%")
                  ->orWhere('gst_number', 'like', "%{$s}%")
            )
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'company_name'       => $data['company_name'],
                'location'           => $data['location'],
                'gst_number'         => $data['gst_number'] ?? null,
                'registration_number'=> $data['registration_number'] ?? null,
                'status'             => 'active',
                'balance'            => 0,
            ]);

            if (! empty($data['spocs'])) {
                foreach ($data['spocs'] as $i => $spoc) {
                    $customer->spocs()->create([
                        'name'       => $spoc['name'],
                        'email'      => $spoc['email'],
                        'phone'      => $spoc['phone'] ?? null,
                        'is_primary' => $i === 0,
                    ]);
                }
            }

            return $customer->load('spocs');
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update([
                'company_name'        => $data['company_name'] ?? $customer->company_name,
                'location'            => $data['location'] ?? $customer->location,
                'gst_number'          => $data['gst_number'] ?? $customer->gst_number,
                'registration_number' => $data['registration_number'] ?? $customer->registration_number,
            ]);

            if (isset($data['spocs'])) {
                $this->syncSpocs($customer, $data['spocs']);
            }

            return $customer->fresh('spocs');
        });
    }

    /**
     * Sync SPOCs intelligently:
     * - Update existing SPOCs by ID (including status changes)
     * - Create new SPOCs (no ID provided)
     * - Mark SPOCs NOT in the new list as INACTIVE (soft delete)
     * - SPOCs with orders are ALWAYS marked inactive (never deleted, preserves history)
     * - Only ACTIVE SPOCs are shown in order cart/catalog
     */
    private function syncSpocs(Customer $customer, array $spocs): void
    {
        // Check if order models exist (modules may not all be installed)
        $hasVoucherOrders = class_exists(\App\Models\VoucherOrder::class);
        $hasSendVoucherOrders = class_exists(\App\Models\SendVoucherOrder::class);

        // Get existing SPOCs with their order counts (only if models exist)
        $query = $customer->spocs();
        if ($hasVoucherOrders) {
            $query->withCount(['voucherOrders as voucher_orders_count']);
        }
        if ($hasSendVoucherOrders) {
            $query->withCount(['sendVoucherOrders as send_voucher_orders_count']);
        }
        
        $existingSpocs = $query->get()->keyBy('id');

        $newSpocIds = collect($spocs)
            ->filter(fn($s) => isset($s['id']))
            ->pluck('id')
            ->toArray();

        // Process each SPOC in the request
        foreach ($spocs as $i => $spocData) {
            $spocId = $spocData['id'] ?? null;
            $isPrimary = $i === 0;
            $requestedStatus = $spocData['status'] ?? 'active'; // Allow status in request

            if ($spocId && $existingSpocs->has($spocId)) {
                // UPDATE existing SPOC (including status change)
                $existingSpocs[$spocId]->update([
                    'name'       => $spocData['name'],
                    'email'      => $spocData['email'],
                    'phone'      => $spocData['phone'] ?? null,
                    'is_primary' => $isPrimary,
                    'status'     => $requestedStatus, // Allow activate/deactivate via API
                ]);
                $existingSpocs->forget($spocId); // Mark as handled
            } else {
                // CREATE new SPOC (always active)
                $customer->spocs()->create([
                    'name'       => $spocData['name'],
                    'email'      => $spocData['email'],
                    'phone'      => $spocData['phone'] ?? null,
                    'is_primary' => $isPrimary,
                    'status'     => 'active',
                ]);
            }
        }

        // Handle REMAINING existing SPOCs (not in new list)
        // These are SPOCs that were removed from the UI - mark them INACTIVE
        foreach ($existingSpocs as $spoc) {
            // Check if SPOC has orders (only if models exist)
            $hasOrders = false;
            if ($hasVoucherOrders && isset($spoc->voucher_orders_count)) {
                $hasOrders = $hasOrders || ($spoc->voucher_orders_count > 0);
            }
            if ($hasSendVoucherOrders && isset($spoc->send_voucher_orders_count)) {
                $hasOrders = $hasOrders || ($spoc->send_voucher_orders_count > 0);
            }

            // NEVER delete SPOCs - always mark inactive to preserve history
            // SPOCs with orders MUST stay (referenced by orders via nullOnDelete FK)
            // SPOCs without orders also marked inactive (soft delete pattern)
            $spoc->update([
                'status' => 'inactive',
                'is_primary' => false, // Inactive SPOCs cannot be primary
            ]);
        }

        // Ensure at least one ACTIVE primary SPOC exists
        $this->ensureActivePrimarySpoc($customer);
    }

    private function ensureActivePrimarySpoc(Customer $customer): void
    {
        $hasActivePrimary = $customer->spocs()
            ->where('is_primary', true)
            ->where('status', 'active')
            ->exists();
        
        if (! $hasActivePrimary) {
            // Promote oldest ACTIVE SPOC to primary
            $customer->spocs()
                ->where('status', 'active')
                ->oldest()
                ->first()
                ?->update(['is_primary' => true]);
        }
    }

    /**
     * Get only ACTIVE SPOCs for a customer (for order cart/catalog)
     */
    public function getActiveSpocs(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerSpoc::where('customer_id', $customerId)
            ->where('status', 'active')
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Toggle SPOC status (active/inactive) - requires OTP verification
     */
    public function toggleSpocStatus(int $spocId, string $status, int $userId): \App\Models\CustomerSpoc
    {
        $spoc = CustomerSpoc::findOrFail($spocId);
        
        if (!in_array($status, ['active', 'inactive'])) {
            throw new \Exception('Invalid status. Must be active or inactive.');
        }

        // If activating, ensure it has valid email
        if ($status === 'active') {
            if (empty($spoc->email) || !filter_var($spoc->email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Cannot activate SPOC without valid email.');
            }
        }

        $spoc->update([
            'status' => $status,
            'is_primary' => $status === 'active' ? $spoc->is_primary : false, // Inactive cannot be primary
        ]);

        // If activating and no other active primary, make this primary
        if ($status === 'active') {
            $this->ensureActivePrimarySpoc($spoc->customer);
        }

        return $spoc->fresh();
    }

    /**
     * Initiate OTP verification for SPOC changes
     */
    public function initiateSpocOtp(int $spocId): \App\Models\CustomerSpoc
    {
        $spoc = CustomerSpoc::findOrFail($spocId);
        $otp = $spoc->generateOtp();
        
        // TODO: Send OTP via email/SMS
        // Mail::to($spoc->email)->send(new SpocOtpMail($otp));
        
        // For now, return the OTP (in production, don't return it - send via email)
        return $spoc->fresh();
    }

    /**
     * Verify OTP and apply SPOC changes
     */
    public function verifySpocOtp(int $spocId, string $otp, int $verifiedBy): \App\Models\CustomerSpoc
    {
        $spoc = CustomerSpoc::findOrFail($spocId);
        
        if (!$spoc->verifyOtp($otp, $verifiedBy)) {
            throw new \Exception('Invalid or expired OTP.');
        }

        return $spoc->fresh();
    }

    public function setStatus(Customer $customer, string $status): Customer
    {
        $customer->update(['status' => $status]);
        return $customer;
    }

    public function adjustBalance(Customer $customer, string $type, float $amount, string $note, int $doneBy): CustomerBalanceLog
    {
        return DB::transaction(function () use ($customer, $type, $amount, $note, $doneBy) {
            if ($type === 'credit') {
                $customer->increment('balance', $amount);
            } else {
                if ($customer->balance < $amount) {
                    throw new \Exception('Insufficient balance.');
                }
                $customer->decrement('balance', $amount);
            }

            $customer->refresh();

            return CustomerBalanceLog::create([
                'customer_id'   => $customer->id,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => $customer->balance,
                'note'          => $note,
                'done_by'       => $doneBy,
            ]);
        });
    }
}
