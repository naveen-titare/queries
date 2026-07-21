<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerSpoc;
use App\Models\SendVoucherOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDeliveryOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendVoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $spoc,
        public string $otp,
        public \DateTimeInterface $expiresAt,
        public string $deliveryUrl,
    ) {}

    public function build()
    {
        return $this->subject('🔐 Voucher download OTP for order ' . $this->order->order_number)
            ->view('emails.order-delivery-otp', [
                'order' => $this->order,
                'customer' => $this->customer,
                'spoc' => $this->spoc,
                'otp' => $this->otp,
                'expiresAt' => $this->expiresAt,
                'deliveryUrl' => $this->deliveryUrl,
            ]);
    }
}
