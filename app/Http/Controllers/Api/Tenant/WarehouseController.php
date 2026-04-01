<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\WarehouseResource;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Warehouse::query()->with('branch');

        if ($search = trim((string) request('search', ''))) {
            $query->search($search);
        }

        if ($branchId = request('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if (request()->boolean('active_only', false)) {
            $query->active();
        }

        $paginator = $query->orderBy('id')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Warehouses retrieved successfully.',
            $paginator,
            WarehouseResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'branch_id'   => ['required', 'integer', Rule::exists('branches', 'id')],
            'code'        => ['required', 'string', 'max:20'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        // Código único dentro de la sucursal
        $exists = Warehouse::query()
            ->where('branch_id', $data['branch_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($exists) {
            return ApiResponse::error('The code is already used in this branch.', 422, [
                'code' => ['The code is already used in this branch.'],
            ]);
        }

        $warehouse = Warehouse::query()->create($data);
        $warehouse->load('branch');

        return ApiResponse::created('Warehouse created successfully.', WarehouseResource::make($warehouse)->resolve());
    }

    public function show(string $tenant, Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('branch');

        return ApiResponse::success('Warehouse retrieved successfully.', WarehouseResource::make($warehouse)->resolve());
    }

    public function update(string $tenant, Warehouse $warehouse): JsonResponse
    {
        $data = request()->validate([
            'code'        => ['sometimes', 'string', 'max:20'],
            'name'        => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code']) && $data['code'] !== $warehouse->code) {
            $exists = Warehouse::query()
                ->where('branch_id', $warehouse->branch_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $warehouse->id)
                ->exists();

            if ($exists) {
                return ApiResponse::error('The code is already used in this branch.', 422, [
                    'code' => ['The code is already used in this branch.'],
                ]);
            }
        }

        $warehouse->update($data);
        $warehouse->load('branch');

        return ApiResponse::success('Warehouse updated successfully.', WarehouseResource::make($warehouse)->resolve());
    }

    public function destroy(string $tenant, Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return ApiResponse::success('Warehouse deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
