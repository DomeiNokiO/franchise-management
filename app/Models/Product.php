<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'selling_price', 'is_active'];
    protected $casts = ['selling_price' => 'decimal:2', 'is_active' => 'boolean'];
    public function recipes(): HasMany { return $this->hasMany(Recipe::class); }
    public function saleItems(): HasMany { return $this->hasMany(SaleItem::class); }
}
