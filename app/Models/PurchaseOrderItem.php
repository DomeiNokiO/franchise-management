<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'ingredient_id', 'quantity', 'unit_cost'];
    protected $casts = ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2'];
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
