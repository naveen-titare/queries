<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sanctum does not enforce token expiry out of the box — this callback
        // makes it check the expires_at column we set when issuing access tokens.
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid) {
            if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
                return false;
            }

            return $isValid;
        });
    }
}
