<?php

namespace App\Services\Auth;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Wraps all Google Authenticator (TOTP) logic.
 * Requires: composer require pragmarx/google2fa-laravel
 */
class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Returns an otpauth:// URL you can render as a QR code
     * for the user to scan during 2FA setup.
     */
    public function getQrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'Avirqo'),
            $user->email,
            $secret
        );
    }

    /**
     * $window = number of 30-second steps of drift tolerance allowed
     * (accounts for minor clock skew between server and phone).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 4);
    }
}
