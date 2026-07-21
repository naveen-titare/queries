<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserPasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateUserPayload($request, false);

        $plainPassword = Str::password(12);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'status' => $data['status'],
            'is_admin' => $data['is_admin'],
            'module_access' => $this->normalizeModuleAccess($data['module_access'] ?? null, $data['is_admin']),
            'password' => $plainPassword,
        ]);

        Mail::to($user->email)->send(new UserPasswordResetMail($user, $plainPassword, true));

        return response()->json([
            'message' => 'User created successfully. A password email has been sent.',
            'user' => $this->formatUser($user->fresh()),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateUserPayload($request, true, $user->id);

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'employee_id' => array_key_exists('employee_id', $data) ? $data['employee_id'] : $user->employee_id,
            'status' => $data['status'] ?? $user->status ?? 'active',
            'is_admin' => array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : (bool) $user->is_admin,
        ]);

        if (array_key_exists('module_access', $data)) {
            $user->module_access = $this->normalizeModuleAccess($data['module_access'], (bool) ($data['is_admin'] ?? $user->is_admin));
        } elseif ($user->is_admin) {
            $user->module_access = $this->defaultModuleAccess(true);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function resetPassword(User $user)
    {
        $plainPassword = Str::password(12);
        $user->update([
            'password' => $plainPassword,
        ]);

        Mail::to($user->email)->send(new UserPasswordResetMail($user, $plainPassword, false));

        return response()->json([
            'message' => 'Password reset email sent successfully.',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()->fresh()),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->phone = $data['phone'] ?? null;

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->password = $data['password'];
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    protected function validateUserPayload(Request $request, bool $isUpdate = false, ?int $userId = null): array
    {
        $rules = [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$isUpdate ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'temporary_disabled'])],
            'is_admin' => ['sometimes', 'boolean'],
            'module_access' => ['sometimes', 'array'],
            'module_access.*.key' => ['required_with:module_access', 'string'],
            'module_access.*.permissions' => ['required_with:module_access', 'array'],
        ];

        return $request->validate($rules);
    }

    protected function formatUser(User $user): array
    {
        $moduleAccess = $user->effectiveModuleAccess();
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_id' => $user->employee_id,
            'status' => $user->status ?? 'active',
            'is_admin' => (bool) $user->is_admin,
            'module_access' => $moduleAccess,
            'profile_photo_url' => $user->profile_photo_path ? Storage::disk('public')->url($user->profile_photo_path) : null,
            'date_added' => optional($user->created_at)?->toDateTimeString(),
            'last_modified' => optional($user->updated_at)?->toDateTimeString(),
        ];
    }

    protected function defaultModuleAccess(bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'permissions' => ['view' => true]],
                ['key' => 'settings', 'label' => 'Settings', 'permissions' => ['view' => true, 'change_password' => true]],
                ['key' => 'customers', 'label' => 'Customer', 'permissions' => ['view' => true, 'edit' => true, 'status' => true]],
                ['key' => 'campaigns', 'label' => 'Campaigns', 'permissions' => ['view' => true, 'edit' => true, 'delete' => true]],
                ['key' => 'send_vouchers', 'label' => 'Send Voucher', 'permissions' => ['view' => true]],
                ['key' => 'order_history', 'label' => 'Order history', 'permissions' => ['view' => true]],
                ['key' => 'billing', 'label' => 'Billing', 'permissions' => ['view' => true, 'proforma_edit' => true, 'tax_finalize' => true, 'payment_edit' => true, 'payment_invalid' => true, 'notes' => true, 'reports_export' => true]],
                ['key' => 'manager_module_access', 'label' => 'Manager Module access', 'permissions' => ['view' => true, 'edit' => true, 'delete' => true]],
            ];
        }

        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'permissions' => ['view' => true]],
            ['key' => 'settings', 'label' => 'Settings', 'permissions' => ['view' => true, 'change_password' => true]],
            ['key' => 'customers', 'label' => 'Customer', 'permissions' => ['view' => false, 'edit' => false, 'status' => false]],
            ['key' => 'campaigns', 'label' => 'Campaigns', 'permissions' => ['view' => false, 'edit' => false, 'delete' => false]],
            ['key' => 'send_vouchers', 'label' => 'Send Voucher', 'permissions' => ['view' => false]],
            ['key' => 'order_history', 'label' => 'Order history', 'permissions' => ['view' => false]],
            ['key' => 'billing', 'label' => 'Billing', 'permissions' => ['view' => false, 'proforma_edit' => false, 'tax_finalize' => false, 'payment_edit' => false, 'payment_invalid' => false, 'notes' => false, 'reports_export' => false]],
            ['key' => 'manager_module_access', 'label' => 'Manager Module access', 'permissions' => ['view' => false, 'edit' => false, 'delete' => false]],
        ];
    }

    protected function normalizeModuleAccess(mixed $moduleAccess, bool $isAdmin): array
    {
        if ($isAdmin) {
            return $this->defaultModuleAccess(true);
        }

        if (!is_array($moduleAccess) || empty($moduleAccess)) {
            return $this->defaultModuleAccess(false);
        }

        $defaults = collect($this->defaultModuleAccess(false))->keyBy('key');

        return collect($moduleAccess)->map(function (array $module) use ($defaults) {
            $default = $defaults->get($module['key']) ?? [
                'key' => $module['key'],
                'label' => $module['label'] ?? Str::headline($module['key']),
                'permissions' => [],
            ];

            return [
                'key' => $default['key'],
                'label' => $module['label'] ?? $default['label'],
                'permissions' => array_merge($default['permissions'] ?? [], $module['permissions'] ?? []),
            ];
        })->values()->all();
    }
}
