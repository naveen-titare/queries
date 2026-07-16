<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorChallenge;
use App\Models\TwoFactorSetupChallenge;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(protected TwoFactorService $twoFactor) {}

    /**
     * Step 1 of login: verify email + password only.
     * Does NOT issue a session. Branches to either:
     *  - a 2FA challenge (existing users with 2FA already set up), or
     *  - a 2FA SETUP challenge (first-time login, no 2FA enrolled yet)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Account temporarily locked due to too many failed 2FA attempts. Try again later.',
            ], 423);
        }

        if (! $user->two_factor_enabled) {
            return $this->beginTwoFactorSetup($user);
        }

        $challengeToken = Str::random(48);

        TwoFactorChallenge::where('user_id', $user->id)->delete();

        TwoFactorChallenge::create([
            'token' => hash('sha256', $challengeToken),
            'user_id' => $user->id,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'message' => '2FA verification required',
            'challenge_token' => $challengeToken,
            'expires_in' => 300,
        ]);
    }

    protected function beginTwoFactorSetup(User $user)
    {
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
            'message' => 'First-time login: set up Google Authenticator to continue',
            'setup_required' => true,
            'setup_token' => $setupToken,
            'otpauth_url' => $this->twoFactor->getQrCodeUrl($user, $secret),
            'expires_in' => 600,
        ]);
    }
}
