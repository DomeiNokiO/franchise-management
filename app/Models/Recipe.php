<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    protected $fillable = ['product_id', 'ingredient_id', 'quantity'];
    protected $casts = ['quantity' => 'decimal:3'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
