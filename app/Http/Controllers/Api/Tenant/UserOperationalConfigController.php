<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\UserOperationalConfigResource;
use App\Models\User;
use App\Models\UserOperationalConfig;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserOperationalConfigController extends Controller
{
    /**
     * El usuario autenticado consulta su propia configuración operacional.
     */
    public function myConfig(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $config = $this->resolveOrCreateConfig($user);

        return ApiResponse::success('Operational config retrieved.', $this->serialize($config));
    }

    /**
     * El usuario autenticado actualiza su propia configuración.
     * Los campos bloqueados no pueden ser cambiados por el propio usuario.
     */
    public function updateMyConfig(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $config = $this->resolveOrCreateConfig($user);

        $data = request()->validate([
            'branch_id'        => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'warehouse_id'     => ['sometimes', 'nullable', 'integer', Rule::exists('warehouses', 'id')],
            'point_of_sale_id' => ['sometimes', 'nullable', 'integer', Rule::exists('points_of_sale', 'id')],
        ]);

        // Respetar bloqueos: ignorar campos bloqueados si el usuario no es SuperAdmin
        if ($config->branch_locked && ! $user->hasRole('SuperAdmin')) {
            unset($data['branch_id']);
        }

        if ($config->warehouse_locked && ! $user->hasRole('SuperAdmin')) {
            unset($data['warehouse_id']);
        }

        if ($config->point_of_sale_locked && ! $user->hasRole('SuperAdmin')) {
            unset($data['point_of_sale_id']);
        }

        $config->update($data);

        return ApiResponse::success('Operational config updated.', $this->serialize($config->fresh()));
    }

    /**
     * Admin consulta la configuración de cualquier usuario.
     */
    public function show(string $tenant, User $user): JsonResponse
    {
        $config = $this->resolveOrCreateConfig($user);

        return ApiResponse::success('Operational config retrieved.', $this->serialize($config));
    }

    /**
     * Admin actualiza la configuración de cualquier usuario (incluye bloqueos).
     */
    public function update(string $tenant, User $user): JsonResponse
    {
        $config = $this->resolveOrCreateConfig($user);

        $data = request()->validate([
            'branch_id'            => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'warehouse_id'         => ['sometimes', 'nullable', 'integer', Rule::exists('warehouses', 'id')],
            'point_of_sale_id'     => ['sometimes', 'nullable', 'integer', Rule::exists('points_of_sale', 'id')],
            'branch_locked'        => ['sometimes', 'boolean'],
            'warehouse_locked'     => ['sometimes', 'boolean'],
            'point_of_sale_locked' => ['sometimes', 'boolean'],
        ]);

        $config->update($data);

        return ApiResponse::success('Operational config updated.', $this->serialize($config->fresh()));
    }

    private function resolveOrCreateConfig(User $user): UserOperationalConfig
    {
        /** @var UserOperationalConfig $config */
        $config = UserOperationalConfig::query()
            ->with(['branch', 'warehouse', 'pointOfSale'])
            ->firstOrCreate(['user_id' => $user->id]);

        return $config;
    }

    private function serialize(UserOperationalConfig $config): array
    {
        $config->load(['branch', 'warehouse', 'pointOfSale']);

        return UserOperationalConfigResource::make($config)->resolve();
    }
}
