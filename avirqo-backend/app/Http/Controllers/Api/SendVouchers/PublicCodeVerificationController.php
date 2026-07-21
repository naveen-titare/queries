<?php

namespace App\Http\Controllers\Api\SendVouchers;

use App\Http\Controllers\Controller;
use App\Models\SendVoucherCode;
use Illuminate\Http\Request;

class PublicCodeVerificationController extends Controller
{
    /**
     * Public endpoint to verify a voucher code by its ID.
     * No authentication required.
     * 
     * GET /api/public/send-vouchers/codes/verify/{id}
     */
    public function verify($id)
    {
        $code = SendVoucherCode::with('product')->find($id);

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found. Please check the Code ID and try again.',
            ], 404);
        }

        // Determine status message for users
        $statusMessage = match($code->status) {
            'available' => 'This voucher is available and has not been assigned yet.',
            'reserved' => 'This voucher is currently reserved and being processed.',
            'sent' => 'This voucher has been successfully delivered to the recipient.',
            'used' => 'This voucher has been securely downloaded by the recipient.',
            'failed' => 'This voucher could not be delivered. Please contact support.',
            default => 'Unknown status.',
        };

        // Check expiry
        $isExpired = $code->expiry_date && $code->expiry_date->isPast();
        $expiryText = $code->expiry_date 
            ? $code->expiry_date->format('d M Y') 
            : 'No expiry date';

        return response()->json([
            'success' => true,
            'voucher' => [
                'code_id' => $code->id,
                'brand' => $code->product?->brand,
                'product_name' => $code->product?->name,
                'denomination' => $code->denomination,
                'currency_code' => $code->currency_code,
                'status' => $code->status,
                'status_message' => $statusMessage,
                'expiry_date' => $expiryText,
                'is_expired' => $isExpired,
                'is_valid' => in_array($code->status, ['sent', 'used'], true) && !$isExpired,
            ],
        ]);
    }
}
