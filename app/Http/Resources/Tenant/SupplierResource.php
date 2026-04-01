<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'name'         => $this->name,
            'tax_id'       => $this->tax_id,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'address'      => $this->address,
            'contact_name' => $this->contact_name,
            'is_active'    => $this->is_active,
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
