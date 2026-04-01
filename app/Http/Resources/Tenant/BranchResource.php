<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'is_active'  => $this->is_active,
            'warehouses_count'     => $this->whenCounted('warehouses'),
            'points_of_sale_count' => $this->whenCounted('pointsOfSale'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
