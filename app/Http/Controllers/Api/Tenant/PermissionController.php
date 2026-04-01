<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PermissionResource;
use App\Services\AuditTrailService;
use App\Services\TenantAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function index(): JsonResponse
    {
        $paginator = Permission::query()
            ->withCount(['users', 'roles'])
            ->orderBy('name');

        if ($search = trim((string) request('search', ''))) {
            $paginator->where('name', 'like', "%{$search}%");
        }

        $paginator = $paginator->paginate($this->resolvePerPage())->withQueryString();

        return ApiResponse::paginated(
            'Permissions retrieved successfully.',
            $paginator,
            PermissionResource::collection($paginator->getCollection())->resolve()
        );
    }

    public function store(): JsonResponse
    {
        $validated = request()->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'api'),
            ],
        ]);

        $permission = Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'api',
        ]);

        $permission = $permission->loadCount(['users', 'roles']);

        $this->auditTrailService->record('tenant.permissions.created', [
            'status_code' => 201,
            'auditable' => $permission,
            'new_values' => $this->serializePermission($permission),
        ]);

        return ApiResponse::created(
            'Permission created successfully.',
            $this->serializePermission($permission)
        );
    }

    public function show(string $tenant, int|string $permissionId): JsonResponse
    {
        $permission = $this->resolvePermission($permissionId)->loadCount(['users', 'roles']);

        return ApiResponse::success(
            'Permission retrieved successfully.',
            $this->serializePermission($permission)
        );
    }

    public function update(string $tenant, int|string $permissionId): JsonResponse
    {
        $permission = $this->resolvePermission($permissionId);
        $permission->loadCount(['users', 'roles']);
        $before = $this->serializePermission($permission);

        $validated = request()->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('permissions', 'name')
                    ->ignore($permission->id)
                    ->where('guard_name', 'api'),
            ],
        ]);

        if (
            TenantAccessService::isDefaultPermissionName($permission->name)
            && $validated['name'] !== $permission->name
        ) {
            throw ValidationException::withMessages([
                'name' => ['Default system permissions cannot be renamed.'],
            ]);
        }

        $permission->update([
            'name' => $validated['name'],
        ]);

        $permission = $permission->fresh()->loadCount(['users', 'roles']);

        $this->auditTrailService->record('tenant.permissions.updated', [
            'status_code' => 200,
            'auditable' => $permission,
            'old_values' => $before,
            'new_values' => $this->serializePermission($permission),
        ]);

        return ApiResponse::success(
            'Permission updated successfully.',
            $this->serializePermission($permission)
        );
    }

    public function destroy(string $tenant, int|string $permissionId): JsonResponse
    {
        $permission = $this->resolvePermission($permissionId);
        $permission->loadCount(['users', 'roles']);
        $before = $this->serializePermission($permission);

        if (TenantAccessService::isDefaultPermissionName($permission->name)) {
            $this->auditTrailService->record('tenant.permissions.delete_denied', [
                'status_code' => 422,
                'auditable' => $permission,
                'meta' => [
                    'reason' => 'default-permission-protected',
                ],
            ]);

            throw ValidationException::withMessages([
                'permission' => ['Default system permissions cannot be deleted.'],
            ]);
        }

        $permission->delete();

        $this->auditTrailService->record('tenant.permissions.deleted', [
            'status_code' => 200,
            'auditable_type' => Permission::class,
            'auditable_id' => (string) $permission->id,
            'old_values' => $before,
        ]);

        return ApiResponse::success('Permission deleted successfully.');
    }

    private function resolvePerPage(): int
    {
        return max(1, min((int) request('per_page', 15), 100));
    }

    private function resolvePermission(int|string $permission): Permission
    {
        return Permission::query()->findOrFail($permission);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePermission(Permission $permission): array
    {
        return PermissionResource::make($permission)->resolve();
    }
}
