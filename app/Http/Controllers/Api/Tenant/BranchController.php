<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\BranchResource;
use App\Models\Branch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Branch::query()->withCount(['warehouses', 'pointsOfSale']);

        if ($search = trim((string) request('search', ''))) {
            $query->search($search);
        }

        if (request()->boolean('active_only', false)) {
            $query->active();
        }

        $paginator = $query->orderBy('id')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Branches retrieved successfully.',
            $paginator,
            BranchResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'code'    => ['required', 'string', 'max:20', Rule::unique('branches', 'code')],
            'name'    => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:150'],
        ]);

        $branch = Branch::query()->create($data);
        $branch->loadCount(['warehouses', 'pointsOfSale']);

        return ApiResponse::created('Branch created successfully.', BranchResource::make($branch)->resolve());
    }

    public function show(string $tenant, Branch $branch): JsonResponse
    {
        $branch->loadCount(['warehouses', 'pointsOfSale']);

        return ApiResponse::success('Branch retrieved successfully.', BranchResource::make($branch)->resolve());
    }

    public function update(string $tenant, Branch $branch): JsonResponse
    {
        $data = request()->validate([
            'code'      => ['sometimes', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch->id)],
            'name'      => ['sometimes', 'string', 'max:150'],
            'address'   => ['sometimes', 'nullable', 'string'],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'     => ['sometimes', 'nullable', 'email', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branch->update($data);
        $branch->loadCount(['warehouses', 'pointsOfSale']);

        return ApiResponse::success('Branch updated successfully.', BranchResource::make($branch)->resolve());
    }

    public function destroy(string $tenant, Branch $branch): JsonResponse
    {
        if ($branch->warehouses()->exists() || $branch->pointsOfSale()->exists()) {
            return ApiResponse::error('Cannot delete a branch that has warehouses or points of sale.', 422);
        }

        $branch->delete();

        return ApiResponse::success('Branch deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
