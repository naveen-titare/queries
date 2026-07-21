<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainPassword,
        public bool $isNewUser = false,
    ) {}

    public function build()
    {
        return $this
            ->subject($this->isNewUser ? 'Your Avirqo account has been created' : 'Your Avirqo password has been reset')
            ->view('emails.user-password-reset')
            ->with([
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'isNewUser' => $this->isNewUser,
            ]);
    }
}
