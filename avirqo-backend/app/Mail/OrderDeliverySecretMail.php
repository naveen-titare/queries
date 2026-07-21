<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerSpoc;
use App\Models\SendVoucherOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDeliverySecretMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendVoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $spoc,
        public string $secretKey,
        public string $deliveryUrl,
        public \DateTimeInterface $expiresAt,
        public ?string $invoicePdf = null,
    ) {}

    public function build()
    {
        $mail = $this->subject('🔑 Voucher delivery access for order ' . $this->order->order_number)
            ->view('emails.order-delivery-secret', [
                'order' => $this->order,
                'customer' => $this->customer,
                'spoc' => $this->spoc,
                'secretKey' => $this->secretKey,
                'deliveryUrl' => $this->deliveryUrl,
                'expiresAt' => $this->expiresAt,
            ]);

        if ($this->invoicePdf) {
            $mail->attachData($this->invoicePdf, "Tax-Invoice-{$this->order->taxInvoice?->invoice_number}.pdf", ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
