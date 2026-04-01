<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperationalConfig extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'warehouse_id',
        'point_of_sale_id',
        'branch_locked',
        'warehouse_locked',
        'point_of_sale_locked',
    ];

    protected $casts = [
        'branch_locked'        => 'boolean',
        'warehouse_locked'     => 'boolean',
        'point_of_sale_locked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }
}
