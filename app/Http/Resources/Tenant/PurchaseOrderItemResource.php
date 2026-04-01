<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'quantity'          => $this->quantity,
            'unit_cost'         => $this->unit_cost,
            'received_quantity' => $this->received_quantity,
            'pending_quantity'  => max(0, (float) $this->quantity - (float) $this->received_quantity),
            'subtotal'          => round((float) $this->quantity * (float) $this->unit_cost, 2),
            'product'           => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
