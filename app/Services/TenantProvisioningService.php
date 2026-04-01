<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\PointOfSale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\DatabaseManager;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly TenantAccessService $tenantAccessService,
    ) {
    }

    /**
     * @return array{tenant: \App\Models\Tenant, admin: \App\Models\User, database: string}
     */
    public function createTenantWithAdmin(array $attributes): array
    {
        $tenantId = Str::lower($attributes['tenant_id']);
        $path = Str::lower($attributes['path'] ?? $tenantId);
        $adminName = $attributes['admin_name'] ?? "{$attributes['company_name']} Admin";
        $databaseAlreadyExists = $this->tenantDatabaseExists($tenantId);

        $this->assertTenantCanBeCreated($tenantId, $path);

        $tenant = null;

        try {
            $tenant = Tenant::create([
                'id' => $tenantId,
                'tenancy_create_database' => ! $databaseAlreadyExists,
                'data' => [
                    'company_name' => $attributes['company_name'],
                    'path' => $path,
                    'admin_email' => $attributes['admin_email'],
                ],
            ]);

            $tenant->createDomain([
                'domain' => $path,
            ]);

            $this->ensureTenantDatabase($tenant);
            $admin = $this->seedPrimaryAdmin($tenant, [
                'name' => $adminName,
                'email' => $attributes['admin_email'],
                'password' => $attributes['admin_password'],
            ]);

            return [
                'tenant' => $tenant->fresh(),
                'admin' => $admin,
                'database' => $tenant->database()->getName(),
            ];
        } catch (Throwable $throwable) {
            if ($tenant?->exists) {
                $tenant->delete();
            }

            throw $throwable;
        }
    }

    /**
     * @return array{tenant: \App\Models\Tenant, admin: \App\Models\User, database: string}
     */
    public function ensureTenantWithAdmin(array $attributes): array
    {
        $tenantId = Str::lower($attributes['tenant_id']);
        $path = Str::lower($attributes['path'] ?? $tenantId);
        $adminName = $attributes['admin_name'] ?? "{$attributes['company_name']} Admin";

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return $this->createTenantWithAdmin($attributes);
        }

        $currentPath = Arr::get($tenant->data, 'path');
        if ($currentPath && $currentPath !== $path) {
            throw ValidationException::withMessages([
                'path' => ['The requested path does not match the existing tenant configuration.'],
            ]);
        }

        if (! $tenant->domains()->where('domain', $path)->exists()) {
            $tenant->createDomain([
                'domain' => $path,
            ]);
        }

        $this->ensureTenantDatabase($tenant);
        $admin = $this->seedPrimaryAdmin($tenant, [
            'name' => $adminName,
            'email' => $attributes['admin_email'],
            'password' => $attributes['admin_password'],
        ]);

        return [
            'tenant' => $tenant->fresh(),
            'admin' => $admin,
            'database' => $tenant->database()->getName(),
        ];
    }

    private function assertTenantCanBeCreated(string $tenantId, string $path): void
    {
        if (Tenant::query()->whereKey($tenantId)->exists()) {
            throw ValidationException::withMessages([
                'tenant_id' => ['The tenant id is already registered.'],
            ]);
        }

        if (Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $path))->exists()) {
            throw ValidationException::withMessages([
                'path' => ['The tenant path is already in use.'],
            ]);
        }
    }

    private function ensureTenantDatabase(Tenant $tenant): void
    {
        $databaseName = $tenant->database()->getName();
        $databaseManager = $tenant->database()->manager();

        if (! $databaseManager->databaseExists($databaseName)) {
            $tenant->database()->makeCredentials();
            $this->databaseManager->ensureTenantCanBeCreated($tenant);
            $databaseManager->createDatabase($tenant);
        }

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->getTenantKey()],
            '--force' => true,
        ]);
    }

    private function tenantDatabaseExists(string $tenantId): bool
    {
        $tenant = new Tenant([
            'id' => $tenantId,
        ]);

        return $tenant->database()->manager()->databaseExists(
            $tenant->database()->getName()
        );
    }

    private function seedPrimaryAdmin(Tenant $tenant, array $adminAttributes): User
    {
        /** @var User $user */
        $user = $tenant->run(function () use ($adminAttributes) {
            $role = $this->tenantAccessService->ensureBaseAuthorizationState();

            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $adminAttributes['email']],
                [
                    'name' => $adminAttributes['name'],
                    'email' => $adminAttributes['email'],
                    'password' => Hash::make($adminAttributes['password']),
                ],
            );

            $this->seedInitialStructure();

            return $this->tenantAccessService->syncUserAccess(
                $user,
                [$role->name],
                []
            );
        });

        return $user;
    }

    private function seedInitialStructure(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'M001'],
            ['name' => 'CASA MATRIZ', 'is_active' => true],
        );

        $warehouse = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'B001'],
            ['name' => 'BODEGA PRINCIPAL', 'is_active' => true],
        );

        PointOfSale::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'P001'],
            ['name' => 'PUNTO DE VENTA 1', 'is_active' => true],
        );
    }
}
