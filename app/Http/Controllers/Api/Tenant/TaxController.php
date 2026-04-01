<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TaxResource;
use App\Models\Tax;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Tax::query();

        if ($search = trim((string) request('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (request()->boolean('active_only', false)) {
            $query->active();
        }

        $paginator = $query->orderBy('id')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Taxes retrieved successfully.',
            $paginator,
            TaxResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'code'                 => ['required', 'string', 'max:20', Rule::unique('taxes', 'code')],
            'name'                 => ['required', 'string', 'max:100'],
            'rate'                 => ['required', 'numeric', 'min:0', 'max:100'],
            'threshold_amount'     => ['nullable', 'numeric', 'min:0'],
            'is_included_in_price' => ['sometimes', 'boolean'],
        ]);

        $tax = Tax::query()->create($data);

        return ApiResponse::created('Tax created successfully.', TaxResource::make($tax)->resolve());
    }

    public function show(string $tenant, Tax $tax): JsonResponse
    {
        return ApiResponse::success('Tax retrieved successfully.', TaxResource::make($tax)->resolve());
    }

    public function update(string $tenant, Tax $tax): JsonResponse
    {
        $data = request()->validate([
            'code'                 => ['sometimes', 'string', 'max:20', Rule::unique('taxes', 'code')->ignore($tax->id)],
            'name'                 => ['sometimes', 'string', 'max:100'],
            'rate'                 => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'threshold_amount'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_included_in_price' => ['sometimes', 'boolean'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $tax->update($data);

        return ApiResponse::success('Tax updated successfully.', TaxResource::make($tax)->resolve());
    }

    public function destroy(string $tenant, Tax $tax): JsonResponse
    {
        $tax->delete();

        return ApiResponse::success('Tax deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
