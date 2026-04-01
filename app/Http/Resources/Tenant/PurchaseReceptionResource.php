<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'notes'             => $this->notes,
            'received_at'       => $this->received_at?->toISOString(),
            'total'             => $this->whenLoaded('items', fn () => $this->totalAmount()),
            'purchase_order'    => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'warehouse'         => new WarehouseResource($this->whenLoaded('warehouse')),
            'user'              => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items'             => PurchaseReceptionItemResource::collection($this->whenLoaded('items')),
            'account_payable'   => new AccountPayableResource($this->whenLoaded('accountPayable')),
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
