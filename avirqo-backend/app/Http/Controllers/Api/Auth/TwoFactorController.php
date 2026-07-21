<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ReauthToken;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\Auth\SessionTokenService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor,
        protected SessionTokenService $sessionTokens
    ) {}

    protected function maxAttempts(): int
    {
        return (int) config('avirqo_auth.two_factor_max_attempts', 3);
    }

    protected function lockoutMinutes(): int
    {
        return (int) config('avirqo_auth.two_factor_lockout_minutes', 15);
    }

    /**
     * Step 2 of login: verify the Google Authenticator code that
     * matches the challenge issued by LoginController::login().
     */
    public function verifyLogin(Request $request)
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $challenge = TwoFactorChallenge::where('token', hash('sha256', $data['challenge_token']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $challenge) {
            return response()->json(['message' => 'Challenge expired or invalid. Please log in again.'], 401);
        }

        $user = User::findOrFail($challenge->user_id);

        if (! $this->twoFactor->verifyCode($user->google2fa_secret, $data['code'])) {
            return $this->handleFailedAttempt($challenge, null, $user);
        }

        $challenge->delete();
        $user->update(['failed_2fa_attempts' => 0, 'locked_until' => null]);

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
     * Called by the frontend once it notices the 6hr access token has expired.
     * Just confirms the reauth token is still valid so the UI can show the 2FA prompt.
     */
    public function reauthRequest(Request $request)
    {
        $data = $request->validate(['reauth_token' => ['required', 'string']]);

        $token = $this->sessionTokens->findValidReauthToken($data['reauth_token']);

        if (! $token) {
            return response()->json(['message' => 'Session fully expired. Please log in again.'], 401);
        }

        return response()->json(['message' => '2FA code required to resume session']);
    }

    /**
     * Re-authentication after 6hr expiry: 2FA code ONLY, no password required.
     */
    public function reauthVerify(Request $request)
    {
        $data = $request->validate([
            'reauth_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $reauthRecord = $this->sessionTokens->findValidReauthToken($data['reauth_token']);

        if (! $reauthRecord) {
            return response()->json(['message' => 'Session fully expired. Please log in again.'], 401);
        }

        $user = User::findOrFail($reauthRecord->user_id);

        if ($user->isLocked()) {
            return response()->json(['message' => 'Account temporarily locked. Please log in again.'], 423);
        }

        if (! $this->twoFactor->verifyCode($user->google2fa_secret, $data['code'])) {
            return $this->handleFailedAttempt(null, $reauthRecord, $user);
        }

        $reauthRecord->delete();
        $user->update(['failed_2fa_attempts' => 0, 'locked_until' => null]);

        $access = $this->sessionTokens->issueAccessToken($user);
        $newReauthToken = $this->sessionTokens->issueReauthToken($user);

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
            'reauth_token' => $newReauthToken,
        ]);
    }

    /**
     * Shared "3 wrong codes -> logged out" logic for both the login challenge
     * and the reauth token. Exactly one of $challenge / $reauthRecord is passed.
     */
    protected function handleFailedAttempt(?TwoFactorChallenge $challenge, ?ReauthToken $reauthRecord, User $user)
    {
        $record = $challenge ?? $reauthRecord;
        $record->increment('attempts');

        if ($record->attempts >= $this->maxAttempts()) {
            $record->delete();
            $user->update([
                'failed_2fa_attempts' => 0,
                'locked_until' => now()->addMinutes($this->lockoutMinutes()),
            ]);

            return response()->json([
                'message' => 'Too many incorrect codes. You have been logged out. Please log in again.',
                'locked' => true,
            ], 423);
        }

        return response()->json([
            'message' => 'Incorrect code.',
            'attempts_remaining' => $this->maxAttempts() - $record->attempts,
        ], 422);
    }
}
