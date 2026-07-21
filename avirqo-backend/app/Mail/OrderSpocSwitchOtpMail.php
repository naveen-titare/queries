<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerSpoc;
use App\Models\SendVoucherOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderSpocSwitchOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendVoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $currentSpoc,
        public CustomerSpoc $newSpoc,
        public string $otp,
        public \Illuminate\Support\Carbon $expiresAt
    ) {}

    public function build()
    {
        return $this->subject('🔐 OTP Verification Required: SPOC switch for order #' . $this->order->order_number)
            ->view('emails.order-spoc-switch-otp', [
                'order' => $this->order,
                'customer' => $this->customer,
                'currentSpoc' => $this->currentSpoc,
                'newSpoc' => $this->newSpoc,
                'otp' => $this->otp,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
