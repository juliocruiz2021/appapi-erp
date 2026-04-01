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
    // -------------------------------------------------------------------------
    // Permisos base del sistema — nunca eliminables
    // -------------------------------------------------------------------------
    public const DEFAULT_PERMISSION_NAMES = [
        // Sistema — usuarios y permisos
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',

        // Estructura organizacional
        'branches.view',
        'branches.create',
        'branches.update',
        'branches.delete',
        'warehouses.view',
        'warehouses.create',
        'warehouses.update',
        'warehouses.delete',
        'pos.view',
        'pos.create',
        'pos.update',
        'pos.delete',
        'taxes.view',
        'taxes.create',
        'taxes.update',
        'taxes.delete',
        'user-config.view',
        'user-config.update',

        // Inventario
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
        'units.view',
        'units.create',
        'units.update',
        'units.delete',
        'stock.view',
        'stock.adjust',
        'stock-movements.view',
        'stock-movements.create',

        // Compras
        'suppliers.view',
        'suppliers.create',
        'suppliers.update',
        'suppliers.delete',
        'purchase-orders.view',
        'purchase-orders.create',
        'purchase-orders.update',
        'purchase-orders.approve',
        'purchase-orders.delete',
        'purchase-receptions.view',
        'purchase-receptions.create',
        'accounts-payable.view',
        'accounts-payable.pay',

        // Ventas
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'quotes.view',
        'quotes.create',
        'quotes.update',
        'quotes.approve',
        'quotes.delete',
        'sales-orders.view',
        'sales-orders.create',
        'sales-orders.update',
        'sales-orders.approve',
        'sales-orders.delete',
        'invoices.view',
        'invoices.create',
        'invoices.cancel',
        'accounts-receivable.view',
        'accounts-receivable.collect',

        // Contabilidad
        'accounts.view',
        'accounts.create',
        'accounts.update',
        'accounts.delete',
        'journal-entries.view',
        'journal-entries.create',
        'journal-entries.update',
        'journal-entries.delete',
        'periods.view',
        'periods.close',

        // RRHH
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'payroll.view',
        'payroll.create',
        'payroll.approve',
        'attendance.view',
        'attendance.create',
        'attendance.update',
        'vacations.view',
        'vacations.create',
        'vacations.approve',

        // Reportes
        'reports.sales',
        'reports.inventory',
        'reports.purchases',
        'reports.financial',
        'reports.rrhh',
        'reports.audit',
    ];

    // -------------------------------------------------------------------------
    // Matriz de roles y sus permisos
    // SuperAdmin recibe todos los permisos — los demás solo los de su función
    // -------------------------------------------------------------------------
    private const ROLE_PERMISSIONS = [
        'Administrador' => [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'pos.view', 'pos.create', 'pos.update', 'pos.delete',
            'taxes.view', 'taxes.create', 'taxes.update', 'taxes.delete',
            'user-config.view', 'user-config.update',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'units.view', 'units.create', 'units.update', 'units.delete',
            'stock.view', 'stock.adjust',
            'stock-movements.view', 'stock-movements.create',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update',
            'purchase-orders.approve', 'purchase-orders.delete',
            'purchase-receptions.view', 'purchase-receptions.create',
            'accounts-payable.view', 'accounts-payable.pay',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'quotes.view', 'quotes.create', 'quotes.update', 'quotes.approve', 'quotes.delete',
            'sales-orders.view', 'sales-orders.create', 'sales-orders.update',
            'sales-orders.approve', 'sales-orders.delete',
            'invoices.view', 'invoices.create', 'invoices.cancel',
            'accounts-receivable.view', 'accounts-receivable.collect',
            'accounts.view',
            'journal-entries.view',
            'periods.view',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'payroll.view', 'payroll.approve',
            'attendance.view', 'attendance.create', 'attendance.update',
            'vacations.view', 'vacations.create', 'vacations.approve',
            'reports.sales', 'reports.inventory', 'reports.purchases',
            'reports.financial', 'reports.rrhh', 'reports.audit',
        ],

        'Vendedor' => [
            'user-config.view', 'user-config.update',
            'products.view',
            'stock.view',
            'stock-movements.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
            'sales-orders.view', 'sales-orders.create', 'sales-orders.update',
            'invoices.view', 'invoices.create',
            'accounts-receivable.view', 'accounts-receivable.collect',
            'vacations.create',
            'reports.sales',
        ],

        'Bodeguero' => [
            'user-config.view', 'user-config.update',
            'warehouses.view',
            'products.view', 'products.create', 'products.update',
            'categories.view', 'categories.create', 'categories.update',
            'units.view', 'units.create', 'units.update',
            'stock.view', 'stock.adjust',
            'stock-movements.view', 'stock-movements.create',
            'suppliers.view',
            'purchase-orders.view',
            'purchase-receptions.view', 'purchase-receptions.create',
            'vacations.create',
            'reports.inventory', 'reports.purchases',
        ],

        'Contador' => [
            'user-config.view', 'user-config.update',
            'taxes.view',
            'products.view',
            'stock.view',
            'stock-movements.view',
            'suppliers.view',
            'purchase-orders.view',
            'purchase-receptions.view',
            'accounts-payable.view', 'accounts-payable.pay',
            'customers.view',
            'invoices.view', 'invoices.cancel',
            'accounts-receivable.view', 'accounts-receivable.collect',
            'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete',
            'journal-entries.view', 'journal-entries.create', 'journal-entries.update',
            'periods.view', 'periods.close',
            'employees.view',
            'payroll.view',
            'vacations.create',
            'reports.sales', 'reports.inventory', 'reports.purchases',
            'reports.financial', 'reports.rrhh',
        ],

        'RRHH' => [
            'user-config.view', 'user-config.update',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'payroll.view', 'payroll.create',
            'attendance.view', 'attendance.create', 'attendance.update',
            'vacations.view', 'vacations.create', 'vacations.approve',
            'reports.rrhh',
        ],

        'Cajero' => [
            'user-config.view', 'user-config.update',
            'pos.view',
            'products.view',
            'customers.view',
            'sales-orders.view', 'sales-orders.create', 'sales-orders.update',
            'invoices.view', 'invoices.create',
            'accounts-receivable.collect',
            'vacations.create',
        ],
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

        // Crear todos los permisos base
        foreach (self::DEFAULT_PERMISSION_NAMES as $permissionName) {
            Permission::query()->firstOrCreate([
                'name'       => $permissionName,
                'guard_name' => 'api',
            ]);
        }

        $allPermissions = Permission::query()
            ->where('guard_name', 'api')
            ->whereIn('name', self::DEFAULT_PERMISSION_NAMES)
            ->get();

        // SuperAdmin — todos los permisos
        /** @var Role $superAdminRole */
        $superAdminRole = Role::query()->firstOrCreate([
            'name'       => 'SuperAdmin',
            'guard_name' => 'api',
        ]);
        $superAdminRole->syncPermissions($allPermissions);

        // Roles operativos — permisos según matriz
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'api',
            ]);

            $role->syncPermissions(
                $allPermissions->whereIn('name', $permissionNames)->values()
            );
        }

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
