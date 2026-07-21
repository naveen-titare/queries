<?php

namespace App\Http\Controllers\Api\SendVouchers;

use App\Http\Controllers\Controller;
use App\Models\SendVoucherOrder;
use App\Services\SendVouchers\SendVoucherService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicVoucherDeliveryController extends Controller
{
    public function __construct(protected SendVoucherService $service) {}

    public function show(string $orderNumber)
    {
        $order = SendVoucherOrder::with(['customer', 'spoc'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer_name' => $order->customer?->company_name,
            'spoc_name' => $order->spoc_name ?: $order->spoc?->name,
            'spoc_email' => $order->spoc_email ?: $order->spoc?->email,
            'delivery_secret_sent_at' => $order->delivery_secret_sent_at?->toIso8601String(),
            'delivery_downloaded_at' => $order->delivery_downloaded_at?->toIso8601String(),
        ]);
    }

    public function requestOtp(Request $request, string $orderNumber)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'secret_key' => ['required', 'string'],
        ]);

        try {
            return response()->json(
                $this->service->requestDeliveryOtp($orderNumber, $data['email'], $data['secret_key'])
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyOtp(Request $request, string $orderNumber)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'secret_key' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            return response()->json(
                $this->service->verifyDeliveryOtp($orderNumber, $data['email'], $data['secret_key'], $data['otp'])
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function download(Request $request, string $orderNumber): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'secret_key' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        try {
            $result = $this->service->downloadDeliveryExcel($orderNumber, $data['email'], $data['secret_key'], $data['otp']);

            return response()->streamDownload(function () use ($result) {
                echo $result['content'];
            }, $result['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
