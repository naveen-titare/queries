<?php
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
 
Route::get('/user', function (Request $request) {
    $user = $request->user();
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'is_admin' => (bool) $user->is_admin,
        'module_access' => $user->effectiveModuleAccess(),
        'status' => $user->status ?? 'active',
        'phone' => $user->phone ?? null,
        'employee_id' => $user->employee_id ?? null,
        'profile_photo_url' => $user->profile_photo_path ? Storage::disk('public')->url($user->profile_photo_path) : null,
    ]);
})->middleware('auth:sanctum');
 
require __DIR__.'/modules/auth.php';
require __DIR__.'/modules/customers.php';
require __DIR__.'/modules/send-vouchers.php';
require __DIR__.'/modules/campaigns.php';
require __DIR__.'/modules/dashboard.php';
require __DIR__.'/modules/settings.php';
require __DIR__.'/modules/billing.php';
require __DIR__.'/public.php';
