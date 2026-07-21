<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerSpoc;
use App\Models\SendVoucherOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendVoucherOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendVoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $spoc,
        public string $excelContent,  // in-memory Excel, never written to disk
        public string $orderNumber,
    ) {}

    public function build()
    {
        return $this
            ->subject("Your Avirqo Vouchers — Order {$this->orderNumber}")
            ->view('emails.send-voucher-order')
            ->with([
                'spocName' => $this->order->spoc_name ?: $this->spoc->name,
                'spocEmail' => $this->order->spoc_email ?: $this->spoc->email,
            ])
            ->attachData(
                $this->excelContent,
                "Avirqo-Send-Vouchers-{$this->orderNumber}.xlsx",
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
    }
}
