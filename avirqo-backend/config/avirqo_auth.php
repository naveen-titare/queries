<?php

return [
    // How long an access token stays valid before the user must re-authenticate
    'access_token_lifetime_hours' => env('SESSION_LIFETIME_HOURS', 6),

    // How long the "silent 2FA-only reauth" window stays open after that
    'reauth_token_lifetime_days' => env('REAUTH_TOKEN_LIFETIME_DAYS', 30),

    // Wrong 2FA codes allowed (at login, reauth, or setup) before forced logout
    'two_factor_max_attempts' => env('TWO_FACTOR_MAX_ATTEMPTS', 3),

    // How long the account is locked after hitting max attempts
    'two_factor_lockout_minutes' => env('TWO_FACTOR_LOCKOUT_MINUTES', 15),

    // Base URL of the Vue frontend, used to build the emailed 2FA reset link
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
