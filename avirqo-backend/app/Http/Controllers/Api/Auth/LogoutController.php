<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ReauthToken;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        ReauthToken::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
