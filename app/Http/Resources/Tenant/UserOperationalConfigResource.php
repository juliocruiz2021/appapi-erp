<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOperationalConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id'          => $this->user_id,
            'branch'           => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id'   => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ] : null),
            'warehouse'        => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id'   => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ] : null),
            'point_of_sale'    => $this->whenLoaded('pointOfSale', fn () => $this->pointOfSale ? [
                'id'   => $this->pointOfSale->id,
                'code' => $this->pointOfSale->code,
                'name' => $this->pointOfSale->name,
            ] : null),
            'branch_locked'        => $this->branch_locked,
            'warehouse_locked'     => $this->warehouse_locked,
            'point_of_sale_locked' => $this->point_of_sale_locked,
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
