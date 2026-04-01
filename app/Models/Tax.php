<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate',
        'threshold_amount',
        'is_included_in_price',
        'is_active',
    ];

    protected $casts = [
        'rate'               => 'decimal:4',
        'threshold_amount'   => 'decimal:4',
        'is_included_in_price' => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determina si este impuesto aplica dado el total acumulado del documento.
     */
    public function appliesToAmount(float $documentTotal): bool
    {
        if ($this->threshold_amount === null) {
            return true;
        }

        return $documentTotal >= (float) $this->threshold_amount;
    }
}
