<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Product::with(['category', 'unit', 'tax']);

        if ($search = trim((string) request('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if (request()->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $paginator = $query->orderBy('name')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Products retrieved successfully.',
            $paginator,
            ProductResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id'     => ['required', 'integer', 'exists:units,id'],
            'tax_id'      => ['nullable', 'integer', 'exists:taxes,id'],
            'code'        => ['required', 'string', 'max:50', Rule::unique('products', 'code')],
            'barcode'     => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')],
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'cost'        => ['nullable', 'numeric', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $product = Product::query()->create($data);
        $product->load(['category', 'unit', 'tax']);

        return ApiResponse::created('Product created successfully.', ProductResource::make($product)->resolve());
    }

    public function show(string $tenant, Product $product): JsonResponse
    {
        $product->load(['category', 'unit', 'tax', 'stock.warehouse']);

        return ApiResponse::success('Product retrieved successfully.', ProductResource::make($product)->resolve());
    }

    public function update(string $tenant, Product $product): JsonResponse
    {
        $data = request()->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'unit_id'     => ['sometimes', 'integer', 'exists:units,id'],
            'tax_id'      => ['sometimes', 'nullable', 'integer', 'exists:taxes,id'],
            'code'        => ['sometimes', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product->id)],
            'barcode'     => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product->id)],
            'name'        => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'cost'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $product->update($data);
        $product->load(['category', 'unit', 'tax']);

        return ApiResponse::success('Product updated successfully.', ProductResource::make($product)->resolve());
    }

    public function destroy(string $tenant, Product $product): JsonResponse
    {
        if ($product->stockMovements()->exists()) {
            return ApiResponse::error('Cannot delete product with stock movements.', 422);
        }

        $product->stock()->delete();
        $product->delete();

        return ApiResponse::success('Product deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
