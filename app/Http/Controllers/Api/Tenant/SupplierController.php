<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\SupplierResource;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Supplier::withCount('purchaseOrders');

        if ($search = trim((string) request('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if (request()->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $paginator = $query->orderBy('name')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Suppliers retrieved successfully.',
            $paginator,
            SupplierResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'code'         => ['required', 'string', 'max:20', Rule::unique('suppliers', 'code')],
            'name'         => ['required', 'string', 'max:200'],
            'tax_id'       => ['nullable', 'string', 'max:20', Rule::unique('suppliers', 'tax_id')],
            'email'        => ['nullable', 'email', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $supplier = Supplier::query()->create($data);
        $supplier->refresh();

        return ApiResponse::created('Supplier created successfully.', SupplierResource::make($supplier)->resolve());
    }

    public function show(string $tenant, Supplier $supplier): JsonResponse
    {
        $supplier->loadCount('purchaseOrders');

        return ApiResponse::success('Supplier retrieved successfully.', SupplierResource::make($supplier)->resolve());
    }

    public function update(string $tenant, Supplier $supplier): JsonResponse
    {
        $data = request()->validate([
            'code'         => ['sometimes', 'string', 'max:20', Rule::unique('suppliers', 'code')->ignore($supplier->id)],
            'name'         => ['sometimes', 'string', 'max:200'],
            'tax_id'       => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('suppliers', 'tax_id')->ignore($supplier->id)],
            'email'        => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'address'      => ['sometimes', 'nullable', 'string'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $supplier->update($data);

        return ApiResponse::success('Supplier updated successfully.', SupplierResource::make($supplier)->resolve());
    }

    public function destroy(string $tenant, Supplier $supplier): JsonResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return ApiResponse::error('Cannot delete supplier with purchase orders.', 422);
        }

        $supplier->delete();

        return ApiResponse::success('Supplier deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
