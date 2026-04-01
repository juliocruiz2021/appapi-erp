<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'barcode'     => $this->barcode,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'cost'        => $this->cost,
            'is_active'   => $this->is_active,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'unit'        => new UnitResource($this->whenLoaded('unit')),
            'tax'         => new TaxResource($this->whenLoaded('tax')),
            'stock'       => StockResource::collection($this->whenLoaded('stock')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
