<?php

namespace App\Services\Auth;

use App\Models\ReauthToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Handles the two token types used by Avirqo auth:
 *
 * 1. Access token (Sanctum) - short-lived, 6 hours, required on every API request.
 * 2. Reauth token - long-lived, lets the frontend silently resume a session
 *    with a 2FA code ONLY (no password) once the access token expires.
 */
class SessionTokenService
{
    public function accessTokenLifetimeHours(): int
    {
        return (int) config('avirqo_auth.access_token_lifetime_hours', 6);
    }

    public function reauthTokenLifetimeDays(): int
    {
        return (int) config('avirqo_auth.reauth_token_lifetime_days', 30);
    }

    public function issueAccessToken(User $user): array
    {
        $expiresAt = now()->addHours($this->accessTokenLifetimeHours());

        // Sanctum's createToken() accepts an expiration as the 3rd argument.
        $token = $user->createToken('avirqo-session', ['*'], $expiresAt);

        return [
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function issueReauthToken(User $user): string
    {
        // Only one active reauth token per user at a time
        ReauthToken::where('user_id', $user->id)->delete();

        $plainToken = Str::random(64);

        ReauthToken::create([
            'token' => hash('sha256', $plainToken),
            'user_id' => $user->id,
            'attempts' => 0,
            'expires_at' => now()->addDays($this->reauthTokenLifetimeDays()),
        ]);

        return $plainToken;
    }

    public function findValidReauthToken(string $plainToken): ?ReauthToken
    {
        return ReauthToken::where('token', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->first();
    }
}
