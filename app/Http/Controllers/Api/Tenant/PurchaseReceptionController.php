<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PurchaseReceptionResource;
use App\Models\AccountPayable;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReception;
use App\Models\PurchaseReceptionItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseReceptionController extends Controller
{
    public function index(): JsonResponse
    {
        $query = PurchaseReception::with(['supplier' => fn ($q) => $q->select('id', 'name')])
            ->with('warehouse');

        if ($search = trim((string) request('search', ''))) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($purchaseOrderId = request('purchase_order_id')) {
            $query->where('purchase_order_id', $purchaseOrderId);
        }

        $paginator = $query->latest()->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Receptions retrieved successfully.',
            $paginator,
            PurchaseReceptionResource::collection($paginator->getCollection())->resolve(),
        );
    }

    /**
     * Crear recepción:
     * 1. Registrar items recibidos
     * 2. Actualizar received_quantity en purchase_order_items
     * 3. Actualizar estado de la orden de compra
     * 4. Ingresar stock (stock_in) en la bodega destino
     * 5. Crear cuenta por pagar
     */
    public function store(): JsonResponse
    {
        $data = request()->validate([
            'purchase_order_id'           => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id'                => ['required', 'integer', 'exists:warehouses,id'],
            'received_at'                 => ['required', 'date'],
            'notes'                       => ['nullable', 'string'],
            'due_date'                    => ['required', 'date'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'            => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_cost'           => ['required', 'numeric', 'min:0'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $reception = PurchaseReception::query()->create([
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id'      => $data['warehouse_id'],
                'user_id'           => auth()->id(),
                'code'              => $this->nextCode(),
                'notes'             => $data['notes'] ?? null,
                'received_at'       => $data['received_at'],
            ]);

            $total = 0.0;

            foreach ($data['items'] as $item) {
                PurchaseReceptionItem::query()->create([
                    'purchase_reception_id'  => $reception->id,
                    'product_id'             => $item['product_id'],
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'quantity'               => $item['quantity'],
                    'unit_cost'              => $item['unit_cost'],
                ]);

                // Actualizar received_quantity en la línea de orden si aplica
                if (! empty($item['purchase_order_item_id'])) {
                    $orderItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                    if ($orderItem) {
                        $orderItem->increment('received_quantity', $item['quantity']);
                    }
                }

                // Ingreso de stock
                $stock = Stock::firstOrCreate(
                    ['product_id' => $item['product_id'], 'warehouse_id' => $data['warehouse_id']],
                    ['quantity' => 0, 'min_quantity' => 0],
                );
                $before = (float) $stock->quantity;
                $after  = $before + (float) $item['quantity'];
                $stock->update(['quantity' => $after]);

                StockMovement::query()->create([
                    'product_id'      => $item['product_id'],
                    'warehouse_id'    => $data['warehouse_id'],
                    'user_id'         => auth()->id(),
                    'type'            => 'in',
                    'quantity'        => (float) $item['quantity'],
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                    'reference'       => $reception->code,
                    'notes'           => 'Recepción de compra',
                ]);

                $total += (float) $item['quantity'] * (float) $item['unit_cost'];
            }

            // Actualizar estado de la orden de compra
            if ($data['purchase_order_id'] ?? null) {
                $order = $reception->purchaseOrder()->with('items')->first();
                $order?->updateStatus();
            }

            // Crear cuenta por pagar
            $supplierId = null;
            if ($data['purchase_order_id'] ?? null) {
                $supplierId = $reception->purchaseOrder()->value('supplier_id');
            }

            if ($supplierId && $total > 0) {
                AccountPayable::query()->create([
                    'supplier_id'          => $supplierId,
                    'purchase_reception_id' => $reception->id,
                    'code'                 => 'CXP-' . $reception->code,
                    'amount'               => $total,
                    'paid_amount'          => 0,
                    'status'               => 'pending',
                    'due_date'             => $data['due_date'],
                ]);
            }

            $reception->load(['purchaseOrder.supplier', 'warehouse', 'items.product', 'user', 'accountPayable']);

            return ApiResponse::created(
                'Reception created successfully.',
                PurchaseReceptionResource::make($reception)->resolve(),
            );
        });
    }

    public function show(string $tenant, PurchaseReception $purchaseReception): JsonResponse
    {
        $purchaseReception->load([
            'purchaseOrder.supplier',
            'warehouse',
            'items.product.unit',
            'user',
            'accountPayable.supplier',
        ]);

        return ApiResponse::success(
            'Reception retrieved successfully.',
            PurchaseReceptionResource::make($purchaseReception)->resolve(),
        );
    }

    private function nextCode(): string
    {
        $last = PurchaseReception::query()->max('id') ?? 0;
        return 'REC-' . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
