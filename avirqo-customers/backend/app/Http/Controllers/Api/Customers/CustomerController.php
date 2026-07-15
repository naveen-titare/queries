<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $service) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->service->list($request->only('search', 'status'))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name'        => ['required', 'string', 'max:255'],
            'location'            => ['required', 'string', 'max:255'],
            'gst_number'          => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'spocs'               => ['required', 'array', 'min:1'],
            'spocs.*.name'        => ['required', 'string'],
            'spocs.*.email'       => ['required', 'email'],
            'spocs.*.phone'       => ['nullable', 'string'],
        ]);

        $customer = $this->service->create($data);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json(
            $customer->load(['spocs', 'documents.uploadedBy', 'balanceLogs.doneBy', 'voucherHistory.sentBy'])
        );
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'company_name'        => ['sometimes', 'string', 'max:255'],
            'location'            => ['sometimes', 'string', 'max:255'],
            'gst_number'          => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'spocs'               => ['sometimes', 'array', 'min:1'],
            'spocs.*.name'        => ['required_with:spocs', 'string'],
            'spocs.*.email'       => ['required_with:spocs', 'email'],
            'spocs.*.phone'       => ['nullable', 'string'],
        ]);

        $customer = $this->service->update($customer, $data);

        return response()->json($customer);
    }

    public function setStatus(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,on_hold,inactive'],
        ]);

        $customer = $this->service->setStatus($customer, $data['status']);

        return response()->json($customer);
    }

    public function adjustBalance(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'type'   => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $log = $this->service->adjustBalance(
                $customer,
                $data['type'],
                (float) $data['amount'],
                $data['note'] ?? '',
                $request->user()->id
            );

            return response()->json([
                'balance' => $customer->fresh()->balance,
                'log'     => $log->load('doneBy'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
