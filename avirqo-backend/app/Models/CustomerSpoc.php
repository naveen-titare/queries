<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSpoc extends Model
{
    protected $fillable = ['customer_id', 'name', 'email', 'phone', 'is_primary', 'user_id', 'status', 'otp_code', 'otp_expires_at', 'otp_verified_by', 'otp_verified_at'];

    protected $casts = [
        'is_primary' => 'boolean',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // For SPOC sync logic - check if SPOC has orders before allowing deletion
    public function voucherOrders()
    {
        return $this->hasMany(\App\Models\VoucherOrder::class, 'spoc_id');
    }

    public function sendVoucherOrders()
    {
        return $this->hasMany(\App\Models\SendVoucherOrder::class, 'spoc_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    // OTP Methods
    public function generateOtp(): string
    {
        $otp = (string) random_int(100000, 999999);
        $this->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending_verification',
        ]);
        return $otp;
    }

    public function verifyOtp(string $otp, int $verifiedBy): bool
    {
        if ($this->otp_code !== $otp) {
            return false;
        }

        if ($this->otp_expires_at && $this->otp_expires_at->isPast()) {
            return false;
        }

        $this->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_verified_by' => $verifiedBy,
            'otp_verified_at' => now(),
            'status' => 'active',
        ]);

        return true;
    }

    public function hasPendingOtp(): bool
    {
        return $this->status === 'pending_verification' 
            && $this->otp_code 
            && $this->otp_expires_at 
            && !$this->otp_expires_at->isPast();
    }
}
