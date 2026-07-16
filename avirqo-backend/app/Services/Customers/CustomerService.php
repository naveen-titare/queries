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
                $customer->spocs()->delete();
                foreach ($data['spocs'] as $i => $spoc) {
                    $customer->spocs()->create([
                        'name'       => $spoc['name'],
                        'email'      => $spoc['email'],
                        'phone'      => $spoc['phone'] ?? null,
                        'is_primary' => $i === 0,
                    ]);
                }
            }

            return $customer->fresh('spocs');
        });
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
