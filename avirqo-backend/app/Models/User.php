<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'status',
        'is_admin',
        'profile_photo_path',
        'module_access',
    ];

    // Never exposed in API responses
    protected $hidden = [
        'password',
        'google2fa_secret',
        'pending_google2fa_secret',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'password' => 'hashed',                    // bcrypt, handled automatically by Laravel
        'google2fa_secret' => 'encrypted',           // encrypted at rest in MySQL using APP_KEY
        'pending_google2fa_secret' => 'encrypted',
        'module_access' => 'array',
        'is_admin' => 'boolean',
    ];

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function effectiveModuleAccess(): array
    {
        if ($this->is_admin) {
            return $this->defaultModuleAccess(true);
        }

        $defaults = collect($this->defaultModuleAccess(false))->keyBy('key');
        $current = collect($this->module_access ?: [])->keyBy('key');

        return $defaults->map(function (array $default, string $key) use ($current) {
            $saved = $current->get($key);
            if (! $saved) {
                return $default;
            }

            return [
                'key' => $default['key'],
                'label' => $saved['label'] ?? $default['label'],
                'permissions' => array_merge($default['permissions'] ?? [], $saved['permissions'] ?? []),
            ];
        })->values()->all();
    }

    public function canAccessModule(string $moduleKey, string $permission = 'view'): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $module = collect($this->effectiveModuleAccess())->firstWhere('key', $moduleKey);

        if (! $module) {
            return false;
        }

        $permissions = $module['permissions'] ?? [];

        if ($permission === 'view') {
            return (bool) ($permissions['view'] ?? false);
        }

        return (bool) ($permissions[$permission] ?? false);
    }

    protected function defaultModuleAccess(bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'permissions' => ['view' => true]],
                ['key' => 'settings', 'label' => 'Settings', 'permissions' => ['view' => true, 'change_password' => true]],
                ['key' => 'customers', 'label' => 'Customer', 'permissions' => ['view' => true, 'edit' => true, 'status' => true]],
                ['key' => 'campaigns', 'label' => 'Campaigns', 'permissions' => ['view' => true, 'edit' => true, 'delete' => true]],
                ['key' => 'voucher_inventory', 'label' => 'Voucher Inventory', 'permissions' => ['view' => true]],
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
            ['key' => 'voucher_inventory', 'label' => 'Voucher Inventory', 'permissions' => ['view' => false]],
            ['key' => 'send_vouchers', 'label' => 'Send Voucher', 'permissions' => ['view' => false]],
            ['key' => 'order_history', 'label' => 'Order history', 'permissions' => ['view' => false]],
            ['key' => 'billing', 'label' => 'Billing', 'permissions' => ['view' => false, 'proforma_edit' => false, 'tax_finalize' => false, 'payment_edit' => false, 'payment_invalid' => false, 'notes' => false, 'reports_export' => false]],
            ['key' => 'manager_module_access', 'label' => 'Manager Module access', 'permissions' => ['view' => false, 'edit' => false, 'delete' => false]],
        ];
    }
}
