<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayable extends Model
{
    protected $table = 'accounts_payable';

    protected $fillable = [
        'supplier_id',
        'purchase_reception_id',
        'code',
        'amount',
        'paid_amount',
        'status',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date'    => 'date',
        'paid_at'     => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseReception(): BelongsTo
    {
        return $this->belongsTo(PurchaseReception::class);
    }

    public function remainingAmount(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }
}
