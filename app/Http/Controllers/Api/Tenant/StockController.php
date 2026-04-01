<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\StockMovementResource;
use App\Http\Resources\Tenant\StockResource;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Stock actual por bodega para un producto.
     */
    public function byProduct(string $tenant, Product $product): JsonResponse
    {
        $stock = $product->stock()->with('warehouse')->get();

        return ApiResponse::success(
            'Stock retrieved successfully.',
            StockResource::collection($stock)->resolve(),
        );
    }

    /**
     * Stock de todos los productos en una bodega.
     */
    public function byWarehouse(string $tenant, Warehouse $warehouse): JsonResponse
    {
        $query = Stock::with('product.category')
            ->where('warehouse_id', $warehouse->id);

        if (request()->boolean('low_stock', false)) {
            $query->whereColumn('quantity', '<=', 'min_quantity');
        }

        if ($search = trim((string) request('search', ''))) {
            $query->whereHas('product', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
            );
        }

        $paginator = $query->orderBy('id')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Warehouse stock retrieved successfully.',
            $paginator,
            StockResource::collection($paginator->getCollection())->resolve(),
        );
    }

    /**
     * Registrar entrada de stock (compra, devolución, etc.).
     */
    public function in(string $tenant, Product $product): JsonResponse
    {
        return $this->applyMovement($product, 'in');
    }

    /**
     * Registrar salida de stock (venta, pérdida, etc.).
     */
    public function out(string $tenant, Product $product): JsonResponse
    {
        return $this->applyMovement($product, 'out');
    }

    /**
     * Ajuste manual de stock (inventario físico).
     */
    public function adjust(string $tenant, Product $product): JsonResponse
    {
        $data = request()->validate([
            'warehouse_id'  => ['required', 'integer', 'exists:warehouses,id'],
            'new_quantity'  => ['required', 'numeric', 'min:0'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($product, $data): JsonResponse {
            $stock = Stock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0, 'min_quantity' => 0],
            );

            $before = (float) $stock->quantity;
            $after  = (float) $data['new_quantity'];
            $diff   = abs($after - $before);

            $stock->update(['quantity' => $after]);

            $movement = StockMovement::query()->create([
                'product_id'      => $product->id,
                'warehouse_id'    => $data['warehouse_id'],
                'user_id'         => auth()->id(),
                'type'            => 'adjustment',
                'quantity'        => $diff,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            $movement->load(['product', 'warehouse', 'user']);

            return ApiResponse::created('Stock adjusted successfully.', StockMovementResource::make($movement)->resolve());
        });
    }

    /**
     * Historial de movimientos de un producto.
     */
    public function movements(string $tenant, Product $product): JsonResponse
    {
        $query = $product->stockMovements()
            ->with(['warehouse', 'user'])
            ->latest();

        if ($type = request('type')) {
            $query->where('type', $type);
        }

        if ($warehouseId = request('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $paginator = $query->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Movements retrieved successfully.',
            $paginator,
            StockMovementResource::collection($paginator->getCollection())->resolve(),
        );
    }

    // ─────────────────────────────────────────────

    private function applyMovement(Product $product, string $type): JsonResponse
    {
        $data = request()->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity'     => ['required', 'numeric', 'min:0.0001'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($product, $type, $data): JsonResponse {
            $stock = Stock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0, 'min_quantity' => 0],
            );

            $before   = (float) $stock->quantity;
            $qty      = (float) $data['quantity'];
            $after    = $type === 'in' ? $before + $qty : $before - $qty;

            if ($after < 0) {
                return ApiResponse::error('Insufficient stock. Available: ' . $before, 422);
            }

            $stock->update(['quantity' => $after]);

            $movement = StockMovement::query()->create([
                'product_id'      => $product->id,
                'warehouse_id'    => $data['warehouse_id'],
                'user_id'         => auth()->id(),
                'type'            => $type,
                'quantity'        => $qty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            $movement->load(['product', 'warehouse', 'user']);

            $label = $type === 'in' ? 'Stock in registered successfully.' : 'Stock out registered successfully.';

            return ApiResponse::created($label, StockMovementResource::make($movement)->resolve());
        });
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
