<?php

namespace App\Mail;

use App\Models\VoucherCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VoucherCampaign $campaign,
        public string $otp,
        public array $changes,
        public string $requestedBy,
        public string $contextLabel,
        public \DateTimeInterface $expiresAt
    ) {}

    public function build()
    {
        return $this->subject('🔐 OTP Verification Required: Campaign Changes ' . now()->format('F j, Y'))
            ->view('emails.campaign-otp', [
                'campaign' => $this->campaign,
                'otp' => $this->otp,
                'changes' => $this->changes,
                'requestedBy' => $this->requestedBy,
                'contextLabel' => $this->contextLabel,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
