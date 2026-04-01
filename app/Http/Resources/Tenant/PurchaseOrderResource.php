<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'ordered_at'  => $this->ordered_at?->toDateString(),
            'expected_at' => $this->expected_at?->toDateString(),
            'total'       => $this->whenLoaded('items', fn () => $this->totalAmount()),
            'supplier'    => new SupplierResource($this->whenLoaded('supplier')),
            'user'        => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items'       => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'receptions_count' => $this->whenCounted('receptions'),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
