<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointOfSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'branch_id' => $this->branch_id,
            'code'      => $this->code,
            'name'      => $this->name,
            'is_active' => $this->is_active,
            'branch'    => $this->whenLoaded('branch', fn () => [
                'id'   => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
