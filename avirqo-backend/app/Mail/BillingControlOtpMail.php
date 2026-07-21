<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingControlOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $actionLabel,
        public string $otp,
        public string $documentNumber,
        public string $customerName,
        public float $totalAmount,
        public string $requestedBy,
        public \DateTimeInterface $expiresAt,
        public ?string $piPdfContent = null,
    ) {}

    public function build()
    {
        $mail = $this->subject('OTP Verification Required: ' . $this->actionLabel . ' ' . now()->format('F j, Y'))
            ->view('emails.billing-control-otp', [
                'actionLabel' => $this->actionLabel,
                'otp' => $this->otp,
                'documentNumber' => $this->documentNumber,
                'customerName' => $this->customerName,
                'totalAmount' => $this->totalAmount,
                'requestedBy' => $this->requestedBy,
                'expiresAt' => $this->expiresAt,
            ]);

        if ($this->piPdfContent) {
            $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->documentNumber) ?: 'proforma-invoice';
            $mail->attachData($this->piPdfContent, "{$safeNumber}.pdf", [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
