<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['branch_id', 'ingredient_id', 'user_id', 'quantity', 'balance_after', 'reason', 'reference_type', 'reference_id'];
    protected $casts = ['quantity' => 'decimal:3', 'balance_after' => 'decimal:3'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
