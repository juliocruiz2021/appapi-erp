<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseReception extends Model
{
    protected $table = 'purchase_receptions';

    protected $fillable = [
        'purchase_order_id',
        'warehouse_id',
        'user_id',
        'code',
        'notes',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceptionItem::class);
    }

    public function accountPayable(): HasOne
    {
        return $this->hasOne(AccountPayable::class);
    }

    public function totalAmount(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->quantity * $item->unit_cost);
    }
}
