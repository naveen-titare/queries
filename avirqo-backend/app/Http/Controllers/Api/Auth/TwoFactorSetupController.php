<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorResetMail;
use App\Models\TwoFactorChallenge;
use App\Models\TwoFactorResetRequest;
use App\Models\TwoFactorSetupChallenge;
use App\Models\User;
use App\Services\Auth\SessionTokenService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor,
        protected SessionTokenService $sessionTokens
    ) {}

    protected function maxAttempts(): int
    {
        return (int) config('avirqo_auth.two_factor_max_attempts', 3);
    }

    /**
     * Confirms enrollment (first-time setup OR post-reset re-enrollment):
     * the user has scanned the QR code and enters the code it's showing.
     * On success this is treated as a completed login.
     */
    public function confirm(Request $request)
    {
        $data = $request->validate([
            'setup_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $challenge = TwoFactorSetupChallenge::where('token', hash('sha256', $data['setup_token']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $challenge) {
            return response()->json(['message' => 'Setup session expired. Please log in again.'], 401);
        }

        $user = User::findOrFail($challenge->user_id);

        if (! $user->pending_google2fa_secret) {
            return response()->json(['message' => 'No pending 2FA setup found. Please log in again.'], 400);
        }

        if (! $this->twoFactor->verifyCode($user->pending_google2fa_secret, $data['code'])) {
            $challenge->increment('attempts');

            if ($challenge->attempts >= $this->maxAttempts()) {
                $challenge->delete();
                $user->pending_google2fa_secret = null;
                $user->save();

                return response()->json([
                    'message' => 'Too many incorrect codes. Please log in again to restart setup.',
                    'locked' => true,
                ], 422);
            }

            return response()->json([
                'message' => 'Incorrect code.',
                'attempts_remaining' => $this->maxAttempts() - $challenge->attempts,
            ], 422);
        }

        // Success: promote the pending secret to the permanent one
        $user->google2fa_secret = $user->pending_google2fa_secret;
        $user->pending_google2fa_secret = null;
        $user->two_factor_enabled = true;
        $user->failed_2fa_attempts = 0;
        $user->locked_until = null;
        $user->save();

        $challenge->delete();

        $access = $this->sessionTokens->issueAccessToken($user);
        $reauthToken = $this->sessionTokens->issueReauthToken($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'module_access' => $user->effectiveModuleAccess(),
            ],
            'access_token' => $access['access_token'],
            'access_token_expires_at' => $access['expires_at'],
            'reauth_token' => $reauthToken,
        ]);
    }

    /**
     * Existing user lost their device: emails a one-time reset LINK to their
     * verified email address. We deliberately never email the QR code or
     * secret itself — only a short-lived link that lets them generate a
     * brand new secret once they click it. Requires a valid, already
     * password-verified login challenge, so this can't be triggered by
     * someone who only knows the email address.
     */
    public function requestReset(Request $request)
    {
        $data = $request->validate(['challenge_token' => ['required', 'string']]);

        $loginChallenge = TwoFactorChallenge::where('token', hash('sha256', $data['challenge_token']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $loginChallenge) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        $user = User::findOrFail($loginChallenge->user_id);

        TwoFactorResetRequest::where('user_id', $user->id)->delete();

        $plainToken = Str::random(64);

        TwoFactorResetRequest::create([
            'token' => hash('sha256', $plainToken),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        $resetUrl = rtrim(config('avirqo_auth.frontend_url'), '/')."/2fa/reset/{$plainToken}";
        Mail::to($user->email)->send(new TwoFactorResetMail($resetUrl));

        return response()->json([
            'message' => 'If that account exists, a reset link has been emailed.',
        ]);
    }

    /**
     * User clicked the emailed link: generates a brand new secret (the old
     * one stops working immediately) and returns a fresh QR to scan.
     */
    public function verifyResetToken(Request $request, string $token)
    {
        $resetRequest = TwoFactorResetRequest::where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $resetRequest) {
            return response()->json(['message' => 'This reset link is invalid or has expired.'], 401);
        }

        $user = User::findOrFail($resetRequest->user_id);
        $resetRequest->delete();

        $secret = $this->twoFactor->generateSecret();
        $user->pending_google2fa_secret = $secret;
        $user->save();

        $setupToken = Str::random(48);

        TwoFactorSetupChallenge::where('user_id', $user->id)->delete();

        TwoFactorSetupChallenge::create([
            'token' => hash('sha256', $setupToken),
            'user_id' => $user->id,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'setup_token' => $setupToken,
            'otpauth_url' => $this->twoFactor->getQrCodeUrl($user, $secret),
            'expires_in' => 600,
        ]);
    }
}
