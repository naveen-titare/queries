<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSpoc;
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
            'spocs.*.id'          => ['sometimes', 'integer', 'exists:customer_spocs,id'],
            'spocs.*.name'        => ['required_with:spocs', 'string'],
            'spocs.*.email'       => ['required_with:spocs', 'email'],
            'spocs.*.phone'       => ['nullable', 'string'],
            'spocs.*.status'      => ['sometimes', 'in:active,inactive'],
        ]);

        $customer = $this->service->update($customer, $data);

        return response()->json($customer);
    }

    /**
     * Get only ACTIVE SPOCs for a customer (for order cart/catalog)
     */
    public function getActiveSpocs(Customer $customer)
    {
        $spocs = $this->service->getActiveSpocs($customer->id);
        return response()->json($spocs);
    }

    /**
     * Toggle SPOC status (active/inactive) - requires OTP verification
     * POST /api/customers/{customer}/spocs/{spoc}/status
     * Body: { "status": "active|inactive" }
     */
    public function toggleSpocStatus(Request $request, Customer $customer, CustomerSpoc $spoc)
    {
        // Verify SPOC belongs to customer
        if ($spoc->customer_id !== $customer->id) {
            return response()->json(['message' => 'SPOC does not belong to this customer.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        try {
            $spoc = $this->service->toggleSpocStatus($spoc->id, $data['status'], $request->user()->id);
            return response()->json($spoc);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Initiate OTP for SPOC verification
     * POST /api/customers/{customer}/spocs/{spoc}/otp/initiate
     */
    public function initiateSpocOtp(Customer $customer, CustomerSpoc $spoc)
    {
        // Verify SPOC belongs to customer
        if ($spoc->customer_id !== $customer->id) {
            return response()->json(['message' => 'SPOC does not belong to this customer.'], 403);
        }

        $spoc = $this->service->initiateSpocOtp($spoc->id);
        
        // In production, don't return OTP - send via email/SMS
        // For development/testing, include OTP in response
        return response()->json([
            'message' => 'OTP sent to SPOC email',
            'otp' => $spoc->otp_code, // REMOVE IN PRODUCTION
            'expires_at' => $spoc->otp_expires_at,
        ]);
    }

    /**
     * Verify OTP and apply SPOC changes
     * POST /api/customers/{customer}/spocs/{spoc}/otp/verify
     * Body: { "otp": "123456" }
     */
    public function verifySpocOtp(Request $request, Customer $customer, CustomerSpoc $spoc)
    {
        // Verify SPOC belongs to customer
        if ($spoc->customer_id !== $customer->id) {
            return response()->json(['message' => 'SPOC does not belong to this customer.'], 403);
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            $spoc = $this->service->verifySpocOtp($spoc->id, $data['otp'], $request->user()->id);
            return response()->json($spoc);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
