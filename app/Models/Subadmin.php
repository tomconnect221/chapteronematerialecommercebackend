<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class Subadmin extends Authenticatable
{
    use HasFactory, HasUuids, HasApiTokens;

    public const DEFAULT_PERMISSIONS = [
        'branch.view',
        'branch.update',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
        'orders.view',
        'orders.update',
        'orders.assign_rider',
        'inventory.view',
        'inventory.update',
        'inventory.delete',
        'inventory.restock',
        'profile.view',
        'profile.update',
    ];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'branch_id',
        'is_active',
        'profile_picture',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'bio',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public static function normalizePermissions(?array $permissions): array
    {
        $normalized = [];

        if ($permissions === null) {
            return self::DEFAULT_PERMISSIONS;
        }

        if ($permissions === []) {
            return [];
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            $trimmed = trim($permission);

            if ($trimmed === '') {
                continue;
            }

            if ($trimmed === '*') {
                return self::DEFAULT_PERMISSIONS;
            }

            $normalized[] = $trimmed;
        }

        $result = [];

        foreach ($normalized as $permission) {
            $result[] = $permission;

            if (str_contains($permission, '.')) {
                continue;
            }

            $pagePermissions = match ($permission) {
                'branch' => ['branch.view', 'branch.update'],
                'products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
                'categories' => ['categories.view', 'categories.create', 'categories.update', 'categories.delete'],
                'orders' => ['orders.view', 'orders.update', 'orders.assign_rider'],
                'inventory' => ['inventory.view', 'inventory.update', 'inventory.delete', 'inventory.restock'],
                'profile' => ['profile.view', 'profile.update'],
                default => []
            };

            foreach ($pagePermissions as $pagePermission) {
                $result[] = $pagePermission;
            }
        }

        return array_values(array_unique($result));
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions;

        if ($permissions === null) {
            return true;
        }

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function canAccessPage(string $page): bool
    {
        $pageKey = strtolower(trim($page));

        if ($pageKey === '') {
            return false;
        }

        $pagePermissions = match ($pageKey) {
            'branch' => ['branch.view', 'branch.update'],
            'products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
            'categories' => ['categories.view', 'categories.create', 'categories.update', 'categories.delete'],
            'orders' => ['orders.view', 'orders.update', 'orders.assign_rider'],
            'inventory' => ['inventory.view', 'inventory.update', 'inventory.delete', 'inventory.restock'],
            'profile' => ['profile.view', 'profile.update'],
            default => []
        };

        if (empty($pagePermissions)) {
            return false;
        }

        foreach ($pagePermissions as $pagePermission) {
            if ($this->hasPermission($pagePermission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the branch that owns the subadmin.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getRoleAttribute()
    {
        return 'subadmin';
    }
}
