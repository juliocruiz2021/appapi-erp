<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'quantity'        => $this->quantity,
            'before_quantity' => $this->before_quantity,
            'after_quantity'  => $this->after_quantity,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'product'         => new ProductResource($this->whenLoaded('product')),
            'warehouse'       => new WarehouseResource($this->whenLoaded('warehouse')),
            'user'            => new UserResource($this->whenLoaded('user')),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
