<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['code', 'name', 'address', 'phone', 'email', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function pointsOfSale(): HasMany
    {
        return $this->hasMany(PointOfSale::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%");
        });
    }
}
