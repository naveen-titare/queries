<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerSpoc;
use App\Models\VoucherOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class VoucherOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VoucherOrder $order,
        public Customer $customer,
        public CustomerSpoc $spoc,
        public string $excelContent,  // in-memory Excel, never written to disk
        public string $orderNumber,
    ) {}

    public function build()
    {
        return $this
            ->subject("Your Avirqo Vouchers — Order {$this->orderNumber}")
            ->view('emails.voucher-order')
            ->attachData(
                $this->excelContent,
                "Avirqo-Vouchers-{$this->orderNumber}.xlsx",
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
    }
}
