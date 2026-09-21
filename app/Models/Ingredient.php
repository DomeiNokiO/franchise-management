<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'unit', 'cost', 'reorder_point', 'is_active'];
    protected $casts = ['cost' => 'decimal:2', 'is_active' => 'boolean'];

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
