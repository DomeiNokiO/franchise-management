<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = ['branch_id', 'cashier_id', 'receipt_number', 'status', 'subtotal', 'discount', 'total', 'paid_amount', 'change_amount', 'sold_at'];
    protected $casts = ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2', 'sold_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
}
