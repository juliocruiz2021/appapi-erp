<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantAccessService
{
    public const DEFAULT_PERMISSION_NAMES = [
        // Usuarios y permisos (base)
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',
        // Sucursales
        'branches.view',
        'branches.create',
        'branches.update',
        'branches.delete',
        // Bodegas
        'warehouses.view',
        'warehouses.create',
        'warehouses.update',
        'warehouses.delete',
        // Puntos de venta
        'pos.view',
        'pos.create',
        'pos.update',
        'pos.delete',
        // Impuestos
        'taxes.view',
        'taxes.create',
        'taxes.update',
        'taxes.delete',
        // Configuración operacional de usuario
        'user-config.view',
        'user-config.update',
    ];

    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function defaultPermissionNames(): array
    {
        return self::DEFAULT_PERMISSION_NAMES;
    }

    public static function isDefaultPermissionName(string $permissionName): bool
    {
        return in_array($permissionName, self::DEFAULT_PERMISSION_NAMES, true);
    }

    public function ensureBaseAuthorizationState(): Role
    {
        $this->permissionRegistrar->forgetCachedPermissions();

        foreach (self::DEFAULT_PERMISSION_NAMES as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api',
            ]);
        }

        /** @var Role $superAdminRole */
        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'SuperAdmin',
            'guard_name' => 'api',
        ]);

        $superAdminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', 'api')
                ->whereIn('name', self::DEFAULT_PERMISSION_NAMES)
                ->get()
        );

        $this->permissionRegistrar->forgetCachedPermissions();

        return $superAdminRole->load('permissions');
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function syncUserAccess(User $user, array $roles, array $permissions): User
    {
        DB::transaction(function () use ($user, $roles, $permissions): void {
            $user->syncRoles($roles);
            $user->syncPermissions($permissions);
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $user->fresh(['roles.permissions', 'permissions']);
    }

    /**
     * @param  list<string>|null  $nextRoles
     */
    public function ensureTenantKeepsSuperAdmin(User $user, ?array $nextRoles = null, bool $deleting = false): void
    {
        $currentlySuperAdmin = $user->hasRole('SuperAdmin');

        if (! $currentlySuperAdmin) {
            return;
        }

        $willRemainSuperAdmin = $deleting
            ? false
            : in_array('SuperAdmin', $nextRoles ?? $user->getRoleNames()->all(), true);

        if ($willRemainSuperAdmin) {
            return;
        }

        $superAdminCount = User::query()->role('SuperAdmin', 'api')->count();

        if ($superAdminCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => ['The tenant must keep at least one SuperAdmin user.'],
            ]);
        }
    }
}
