<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PointOfSaleResource;
use App\Models\PointOfSale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PointOfSaleController extends Controller
{
    public function index(): JsonResponse
    {
        $query = PointOfSale::query()->with('branch');

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
            'Points of sale retrieved successfully.',
            $paginator,
            PointOfSaleResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'code'      => ['required', 'string', 'max:20'],
            'name'      => ['required', 'string', 'max:100'],
        ]);

        $exists = PointOfSale::query()
            ->where('branch_id', $data['branch_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($exists) {
            return ApiResponse::error('The code is already used in this branch.', 422, [
                'code' => ['The code is already used in this branch.'],
            ]);
        }

        $pos = PointOfSale::query()->create($data);
        $pos->load('branch');

        return ApiResponse::created('Point of sale created successfully.', PointOfSaleResource::make($pos)->resolve());
    }

    public function show(string $tenant, PointOfSale $pointOfSale): JsonResponse
    {
        $pointOfSale->load('branch');

        return ApiResponse::success('Point of sale retrieved successfully.', PointOfSaleResource::make($pointOfSale)->resolve());
    }

    public function update(string $tenant, PointOfSale $pointOfSale): JsonResponse
    {
        $data = request()->validate([
            'code'      => ['sometimes', 'string', 'max:20'],
            'name'      => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code']) && $data['code'] !== $pointOfSale->code) {
            $exists = PointOfSale::query()
                ->where('branch_id', $pointOfSale->branch_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $pointOfSale->id)
                ->exists();

            if ($exists) {
                return ApiResponse::error('The code is already used in this branch.', 422, [
                    'code' => ['The code is already used in this branch.'],
                ]);
            }
        }

        $pointOfSale->update($data);
        $pointOfSale->load('branch');

        return ApiResponse::success('Point of sale updated successfully.', PointOfSaleResource::make($pointOfSale)->resolve());
    }

    public function destroy(string $tenant, PointOfSale $pointOfSale): JsonResponse
    {
        $pointOfSale->delete();

        return ApiResponse::success('Point of sale deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
