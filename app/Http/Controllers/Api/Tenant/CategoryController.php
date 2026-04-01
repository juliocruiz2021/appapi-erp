<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Category::withCount('products');

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
            'Categories retrieved successfully.',
            $paginator,
            CategoryResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'code'        => ['required', 'string', 'max:20', Rule::unique('categories', 'code')],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $category = Category::query()->create($data);
        $category->refresh();
        $category->loadCount('products');

        return ApiResponse::created('Category created successfully.', CategoryResource::make($category)->resolve());
    }

    public function show(string $tenant, Category $category): JsonResponse
    {
        $category->loadCount('products');

        return ApiResponse::success('Category retrieved successfully.', CategoryResource::make($category)->resolve());
    }

    public function update(string $tenant, Category $category): JsonResponse
    {
        $data = request()->validate([
            'code'        => ['sometimes', 'string', 'max:20', Rule::unique('categories', 'code')->ignore($category->id)],
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return ApiResponse::success('Category updated successfully.', CategoryResource::make($category)->resolve());
    }

    public function destroy(string $tenant, Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return ApiResponse::error('Cannot delete category with associated products.', 422);
        }

        $category->delete();

        return ApiResponse::success('Category deleted successfully.');
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
