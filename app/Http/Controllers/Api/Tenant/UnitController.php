<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\UnitResource;
use App\Models\Unit;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Unit::query();

        if ($search = trim((string) request('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (request()->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $paginator = $query->orderBy('name')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Units retrieved successfully.',
            $paginator,
            UnitResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'code'      => ['required', 'string', 'max:20', Rule::unique('units', 'code')],
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $unit = Unit::query()->create($data);
        $unit->refresh();

        return ApiResponse::created('Unit created successfully.', UnitResource::make($unit)->resolve());
    }

    public function show(string $tenant, Unit $unit): JsonResponse
    {
        return ApiResponse::success('Unit retrieved successfully.', UnitResource::make($unit)->resolve());
    }

    public function update(string $tenant, Unit $unit): JsonResponse
    {
        $data = request()->validate([
            'code'      => ['sometimes', 'string', 'max:20', Rule::unique('units', 'code')->ignore($unit->id)],
            'name'      => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $unit->update($data);

        return ApiResponse::success('Unit updated successfully.', UnitResource::make($unit)->resolve());
    }

    public function destroy(string $tenant, Unit $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            return ApiResponse::error('Cannot delete unit with associated products.', 422);
        }

        $unit->delete();

        return ApiResponse::success('Unit deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
