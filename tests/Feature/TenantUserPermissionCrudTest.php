<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantUserPermissionCrudTest extends TestCase
{
    private string $tenantId = 'crudtest';
    private string $tenantAdminEmail = 'admin@crudtest.com';
    private string $tenantAdminPassword = 'password123';
    /** @var list<string> */
    private array $createdTenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);

        app(TenantProvisioningService::class)->ensureTenantWithAdmin([
            'company_name' => 'CRUD Test Company',
            'tenant_id' => $this->tenantId,
            'path' => $this->tenantId,
            'admin_name' => 'CRUD Test Admin',
            'admin_email' => $this->tenantAdminEmail,
            'admin_password' => $this->tenantAdminPassword,
        ]);

        $this->createdTenantIds = [$this->tenantId];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTenantIds as $tenantId) {
            Tenant::query()->find($tenantId)?->delete();
        }

        parent::tearDown();
    }

    public function test_super_admin_can_manage_users_and_permissions(): void
    {
        $permissionName = 'reports.'.Str::lower(Str::random(8));
        $userEmail = 'operator.'.Str::lower(Str::random(6)).'@crudtest.com';

        $this->get("/{$this->tenantId}/api/v1/me")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $login = $this->postJson("/{$this->tenantId}/api/v1/login", [
            'email' => $this->tenantAdminEmail,
            'password' => $this->tenantAdminPassword,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.');

        $token = $login->json('data.access_token');
        $adminId = $login->json('data.user.id');

        $authHeaders = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];

        $createPermission = $this->withHeaders($authHeaders)
            ->postJson("/{$this->tenantId}/api/v1/permissions", [
                'name' => $permissionName,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $permissionId = $createPermission->json('data.id');

        $this->withHeaders($authHeaders)
            ->getJson("/{$this->tenantId}/api/v1/permissions/{$permissionId}")
            ->assertOk()
            ->assertJsonPath('data.name', $permissionName)
            ->assertJsonPath('message', 'Permission retrieved successfully.');

        $createUser = $this->withHeaders($authHeaders)
            ->postJson("/{$this->tenantId}/api/v1/users", [
                'name' => 'CRUD Test Operator',
                'email' => $userEmail,
                'password' => 'password123',
                'permissions' => [
                    'users.view',
                    $permissionName,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $userId = $createUser->json('data.id');

        $this->withHeaders($authHeaders)
            ->getJson("/{$this->tenantId}/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('data.email', $userEmail)
            ->assertJsonPath('message', 'User retrieved successfully.');

        $this->withHeaders($authHeaders)
            ->patchJson("/{$this->tenantId}/api/v1/users/{$userId}", [
                'name' => 'CRUD Test Operator Updated',
                'permissions' => [
                    'permissions.view',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'CRUD Test Operator Updated')
            ->assertJsonPath('data.direct_permissions.0', 'permissions.view')
            ->assertJsonPath('message', 'User updated successfully.');

        $this->postJson("/{$this->tenantId}/api/v1/login", [
            'email' => $userEmail,
            'password' => 'password123',
        ])->assertOk();

        $adminRelogin = $this->postJson("/{$this->tenantId}/api/v1/login", [
            'email' => $this->tenantAdminEmail,
            'password' => $this->tenantAdminPassword,
        ])->assertOk();

        $authHeaders['Authorization'] = 'Bearer '.$adminRelogin->json('data.access_token');

        $defaultPermissionId = $this->withHeaders($authHeaders)
            ->getJson("/{$this->tenantId}/api/v1/permissions?search=users.view")
            ->assertOk()
            ->json('data.0.id');

        $this->withHeaders($authHeaders)
            ->deleteJson("/{$this->tenantId}/api/v1/users/{$adminId}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own user while authenticated.');

        $this->withHeaders($authHeaders)
            ->deleteJson("/{$this->tenantId}/api/v1/permissions/{$defaultPermissionId}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Default system permissions cannot be deleted.');

        $this->withHeaders($authHeaders)
            ->deleteJson("/{$this->tenantId}/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->withHeaders($authHeaders)
            ->deleteJson("/{$this->tenantId}/api/v1/permissions/{$permissionId}")
            ->assertOk()
            ->assertJsonPath('message', 'Permission deleted successfully.');
    }

    public function test_tenant_token_cannot_be_reused_across_other_tenant_route(): void
    {
        $otherTenantId = 'crudshadow';

        app(TenantProvisioningService::class)->ensureTenantWithAdmin([
            'company_name' => 'CRUD Shadow Company',
            'tenant_id' => $otherTenantId,
            'path' => $otherTenantId,
            'admin_name' => 'CRUD Shadow Admin',
            'admin_email' => 'admin@crudshadow.com',
            'admin_password' => 'password123',
        ]);

        $this->createdTenantIds[] = $otherTenantId;

        $login = $this->postJson("/{$this->tenantId}/api/v1/login", [
            'email' => $this->tenantAdminEmail,
            'password' => $this->tenantAdminPassword,
        ])->assertOk();

        $token = $login->json('data.access_token');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson("/{$otherTenantId}/api/v1/me")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The supplied token does not belong to this tenant.');
    }
}
