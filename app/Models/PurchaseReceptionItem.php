<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceptionItem extends Model
{
    protected $table = 'purchase_reception_items';

    protected $fillable = [
        'purchase_reception_id',
        'product_id',
        'purchase_order_item_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity'  => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function purchaseReception(): BelongsTo
    {
        return $this->belongsTo(PurchaseReception::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
