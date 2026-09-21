<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = ['branch_id', 'created_by', 'approved_by', 'status', 'ordered_at', 'approved_at', 'received_at', 'notes'];
    protected $casts = ['ordered_at' => 'datetime', 'approved_at' => 'datetime', 'received_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}
