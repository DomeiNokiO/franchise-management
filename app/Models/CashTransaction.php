<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'type', 'category', 'amount', 'description', 'occurred_at'];
    protected $casts = ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
