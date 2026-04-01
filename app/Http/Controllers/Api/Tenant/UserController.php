<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\UserResource;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\TenantAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly TenantAccessService $tenantAccessService,
    ) {
    }

    public function index(): JsonResponse
    {
        $paginator = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('id');

        if ($search = trim((string) request('search', ''))) {
            $paginator->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = request('role')) {
            $paginator->role($role, 'api');
        }

        if ($permission = request('permission')) {
            $paginator->permission($permission);
        }

        $paginator = $paginator->paginate($this->resolvePerPage())->withQueryString();

        return ApiResponse::paginated(
            'Users retrieved successfully.',
            $paginator,
            UserResource::collection($paginator->getCollection())->resolve()
        );
    }

    public function store(): JsonResponse
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'api')],
        ]);

        $user = User::query()->create(Arr::only($validated, ['name', 'email', 'password']));

        $user = $this->tenantAccessService->syncUserAccess(
            $user,
            $validated['roles'] ?? [],
            $validated['permissions'] ?? [],
        );

        $user = $user->fresh(['roles.permissions', 'permissions']);

        $this->auditTrailService->record('tenant.users.created', [
            'status_code' => 201,
            'auditable' => $user,
            'new_values' => $this->serializeUser($user),
        ]);

        return ApiResponse::created('User created successfully.', $this->serializeUser($user));
    }

    public function show(string $tenant, User $user): JsonResponse
    {
        $user = $user->load(['roles.permissions', 'permissions']);

        return ApiResponse::success('User retrieved successfully.', $this->serializeUser($user));
    }

    public function update(string $tenant, User $user): JsonResponse
    {
        $user->load(['roles.permissions', 'permissions']);
        $before = $this->serializeUser($user);

        $validated = request()->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:120'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'api')],
        ]);

        $payload = Arr::only($validated, ['name', 'email']);

        if (($validated['password'] ?? null) !== null) {
            $payload['password'] = $validated['password'];
        }

        $user->fill($payload)->save();
        $user->loadMissing(['roles.permissions', 'permissions']);

        if (array_key_exists('roles', $validated) || array_key_exists('permissions', $validated)) {
            $roles = array_key_exists('roles', $validated)
                ? $validated['roles']
                : $user->roles->pluck('name')->all();

            $permissions = array_key_exists('permissions', $validated)
                ? $validated['permissions']
                : $user->permissions->pluck('name')->all();

            $this->tenantAccessService->ensureTenantKeepsSuperAdmin($user, $roles);

            $user = $this->tenantAccessService->syncUserAccess($user, $roles, $permissions);
        }

        $user = $user->fresh(['roles.permissions', 'permissions']);

        $this->auditTrailService->record('tenant.users.updated', [
            'status_code' => 200,
            'auditable' => $user,
            'old_values' => $before,
            'new_values' => $this->serializeUser($user),
        ]);

        return ApiResponse::success('User updated successfully.', $this->serializeUser($user));
    }

    public function destroy(string $tenant, User $user): JsonResponse
    {
        $authenticatedUser = Auth::guard('api')->user();

        if ($authenticatedUser && (int) $authenticatedUser->getAuthIdentifier() === (int) $user->id) {
            $this->auditTrailService->record('tenant.users.delete_denied', [
                'status_code' => 422,
                'auditable' => $user,
                'meta' => [
                    'reason' => 'self-delete-blocked',
                ],
            ]);

            return ApiResponse::error('You cannot delete your own user while authenticated.', 422);
        }

        $before = $this->serializeUser($user->load(['roles.permissions', 'permissions']));

        $this->tenantAccessService->ensureTenantKeepsSuperAdmin($user, deleting: true);
        $user->delete();

        $this->auditTrailService->record('tenant.users.deleted', [
            'status_code' => 200,
            'auditable_type' => User::class,
            'auditable_id' => (string) $user->id,
            'old_values' => $before,
        ]);

        return ApiResponse::success('User deleted successfully.');
    }

    private function resolvePerPage(): int
    {
        return max(1, min((int) request('per_page', 15), 100));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return UserResource::make($user)->resolve();
    }
}
