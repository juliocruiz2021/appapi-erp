<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPayableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'amount'               => $this->amount,
            'paid_amount'          => $this->paid_amount,
            'remaining_amount'     => $this->remainingAmount(),
            'status'               => $this->status,
            'due_date'             => $this->due_date?->toDateString(),
            'paid_at'              => $this->paid_at?->toISOString(),
            'notes'                => $this->notes,
            'supplier'             => new SupplierResource($this->whenLoaded('supplier')),
            'purchase_reception'   => new PurchaseReceptionResource($this->whenLoaded('purchaseReception')),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
