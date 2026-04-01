<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $query = PurchaseOrder::with('supplier')
            ->withCount('receptions');

        if ($search = trim((string) request('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = request('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        $paginator = $query->latest()->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Purchase orders retrieved successfully.',
            $paginator,
            PurchaseOrderResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'ordered_at'          => ['required', 'date'],
            'expected_at'         => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'notes'               => ['nullable', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_cost'   => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $data['supplier_id'],
                'user_id'     => auth()->id(),
                'code'        => $this->nextCode(),
                'status'      => 'draft',
                'notes'       => $data['notes'] ?? null,
                'ordered_at'  => $data['ordered_at'],
                'expected_at' => $data['expected_at'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                ]);
            }

            $order->load(['supplier', 'items.product', 'user']);

            return ApiResponse::created(
                'Purchase order created successfully.',
                PurchaseOrderResource::make($order)->resolve(),
            );
        });
    }

    public function show(string $tenant, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'items.product.unit', 'user', 'receptions']);

        return ApiResponse::success(
            'Purchase order retrieved successfully.',
            PurchaseOrderResource::make($purchaseOrder)->resolve(),
        );
    }

    public function update(string $tenant, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! in_array($purchaseOrder->status, ['draft'])) {
            return ApiResponse::error('Only draft orders can be edited.', 422);
        }

        $data = request()->validate([
            'supplier_id'         => ['sometimes', 'integer', 'exists:suppliers,id'],
            'ordered_at'          => ['sometimes', 'date'],
            'expected_at'         => ['sometimes', 'nullable', 'date'],
            'notes'               => ['sometimes', 'nullable', 'string'],
            'items'               => ['sometimes', 'array', 'min:1'],
            'items.*.product_id'  => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit_cost'   => ['required_with:items', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($purchaseOrder, $data): JsonResponse {
            $purchaseOrder->update(collect($data)->except('items')->toArray());

            if (isset($data['items'])) {
                $purchaseOrder->items()->delete();
                foreach ($data['items'] as $item) {
                    PurchaseOrderItem::query()->create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id'        => $item['product_id'],
                        'quantity'          => $item['quantity'],
                        'unit_cost'         => $item['unit_cost'],
                    ]);
                }
            }

            $purchaseOrder->load(['supplier', 'items.product', 'user']);

            return ApiResponse::success(
                'Purchase order updated successfully.',
                PurchaseOrderResource::make($purchaseOrder)->resolve(),
            );
        });
    }

    /**
     * Cambiar estado: draft → sent, cualquier estado permitido → cancelled.
     */
    public function updateStatus(string $tenant, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = request()->validate([
            'status' => ['required', 'in:sent,cancelled'],
        ]);

        $allowed = match ($purchaseOrder->status) {
            'draft'   => ['sent', 'cancelled'],
            'sent'    => ['cancelled'],
            'partial' => ['cancelled'],
            default   => [],
        };

        if (! in_array($data['status'], $allowed)) {
            return ApiResponse::error("Cannot transition from '{$purchaseOrder->status}' to '{$data['status']}'.", 422);
        }

        $purchaseOrder->update(['status' => $data['status']]);

        return ApiResponse::success(
            'Status updated successfully.',
            PurchaseOrderResource::make($purchaseOrder)->resolve(),
        );
    }

    public function destroy(string $tenant, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return ApiResponse::error('Only draft orders can be deleted.', 422);
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return ApiResponse::success('Purchase order deleted successfully.');
    }

    private function nextCode(): string
    {
        $last = PurchaseOrder::query()->max('id') ?? 0;
        return 'OC-' . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
