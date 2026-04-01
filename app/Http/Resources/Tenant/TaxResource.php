<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'rate'                 => (float) $this->rate,
            'threshold_amount'     => $this->threshold_amount !== null ? (float) $this->threshold_amount : null,
            'is_included_in_price' => $this->is_included_in_price,
            'is_active'            => $this->is_active,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
