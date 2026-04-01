<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity'     => $this->quantity,
            'min_quantity' => $this->min_quantity,
            'is_low_stock' => (float) $this->quantity <= (float) $this->min_quantity,
            'warehouse'    => new WarehouseResource($this->whenLoaded('warehouse')),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
