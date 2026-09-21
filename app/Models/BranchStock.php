<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchStock extends Model
{
    protected $fillable = ['branch_id', 'ingredient_id', 'quantity', 'average_cost'];
    protected $casts = ['quantity' => 'decimal:3', 'average_cost' => 'decimal:2'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
