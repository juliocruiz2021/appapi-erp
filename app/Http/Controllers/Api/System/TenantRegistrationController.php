<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantRegistrationController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly TenantProvisioningService $tenantProvisioningService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'tenant_id' => ['required', 'string', 'max:64', 'alpha_dash:ascii'],
            'path' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'admin_name' => ['nullable', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_password' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        $result = $this->tenantProvisioningService->createTenantWithAdmin($payload);
        $tenant = $result['tenant'];
        $response = [
            'tenant' => [
                'id' => $tenant->id,
                'company_name' => $tenant->data['company_name'] ?? null,
                'path' => $tenant->data['path'] ?? null,
                'database' => $result['database'],
                'login_url' => sprintf('/%s/api/v1/login', $tenant->data['path'] ?? $tenant->id),
            ],
            'admin' => [
                'email' => $result['admin']->email,
                'role' => 'SuperAdmin',
            ],
        ];

        $this->auditTrailService->record('system.tenant.registered', [
            'status_code' => 201,
            'auditable' => $tenant,
            'new_values' => $response,
            'meta' => [
                'input' => $payload,
            ],
        ]);

        return ApiResponse::created('Tenant registered successfully.', $response);
    }
}
