<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name', 'location', 'gst_number',
        'registration_number', 'status', 'balance',
        // Order OTP fields
        'order_otp_code', 'order_otp_expires_at', 'order_otp_verified_at', 'order_otp_verified_by',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'order_otp_expires_at' => 'datetime',
        'order_otp_verified_at' => 'datetime',
    ];

    public function spocs()
    {
        return $this->hasMany(CustomerSpoc::class);
    }

    public function documents()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function balanceLogs()
    {
        return $this->hasMany(CustomerBalanceLog::class)->latest();
    }

    public function voucherHistory()
    {
        return $this->hasMany(CustomerVoucherHistory::class)->latest('sent_at');
    }

    // Order OTP Methods
    public function generateOrderOtp(): string
    {
        $otp = (string) random_int(100000, 999999);
        $this->update([
            'order_otp_code' => $otp,
            'order_otp_expires_at' => now()->addMinutes(10),
            'order_otp_verified_at' => null,
            'order_otp_verified_by' => null,
        ]);
        return $otp;
    }

    public function verifyOrderOtp(string $otp, int $verifiedBy): bool
    {
        if ($this->order_otp_code !== $otp) {
            return false;
        }

        if ($this->order_otp_expires_at && $this->order_otp_expires_at->isPast()) {
            return false;
        }

        $this->update([
            'order_otp_code' => null,
            'order_otp_expires_at' => null,
            'order_otp_verified_at' => now(),
            'order_otp_verified_by' => $verifiedBy,
        ]);

        return true;
    }

    public function hasValidOrderOtp(): bool
    {
        return $this->order_otp_code
            && $this->order_otp_expires_at
            && !$this->order_otp_expires_at->isPast()
            && !$this->order_otp_verified_at;
    }
}
