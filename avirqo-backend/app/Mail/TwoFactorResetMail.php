<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $resetUrl) {}

    public function build()
    {
        return $this->subject('Reset your Avirqo authenticator')
            ->view('emails.two-factor-reset');
    }
}
