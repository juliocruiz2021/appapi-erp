<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'supplier_id',
        'user_id',
        'code',
        'status',
        'notes',
        'ordered_at',
        'expected_at',
    ];

    protected $casts = [
        'ordered_at'  => 'date',
        'expected_at' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(PurchaseReception::class);
    }

    public function totalAmount(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->quantity * $item->unit_cost);
    }

    public function updateStatus(): void
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return;
        }

        $allReceived = $items->every(
            fn ($item) => (float) $item->received_quantity >= (float) $item->quantity
        );

        $anyReceived = $items->some(fn ($item) => (float) $item->received_quantity > 0);

        if ($allReceived) {
            $this->update(['status' => 'received']);
        } elseif ($anyReceived) {
            $this->update(['status' => 'partial']);
        }
    }
}
