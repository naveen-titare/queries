<?php

namespace App\Mail;

use App\Models\SendVoucherOrder;
use App\Models\Customer;
use App\Models\CustomerSpoc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendVoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $spoc,
        public string $otp,
        public array $itemsSummary,
        public ?string $draftInvoicePdf = null,
    ) {}

    public function build()
    {
        $totalAmount = collect($this->itemsSummary)->sum('total');
        $totalQuantity = collect($this->itemsSummary)->sum('quantity');

        $mail = $this->subject('🔐 OTP Verification Required: Send Voucher Order #' . $this->order->order_number)
            ->view('emails.order-otp', [
                'order' => $this->order,
                'customer' => $this->customer,
                'spoc' => $this->spoc,
                'otp' => $this->otp,
                'items' => $this->itemsSummary,
                'totalAmount' => $totalAmount,
                'totalQuantity' => $totalQuantity,
            ]);

        if ($this->draftInvoicePdf) {
            $mail->attachData($this->draftInvoicePdf, "Draft-Tax-Invoice-{$this->order->order_number}.pdf", ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
