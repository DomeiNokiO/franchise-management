<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    protected $fillable = ['branch_id', 'opened_by', 'closed_by', 'status', 'opening_balance', 'closing_balance', 'expected_balance', 'variance', 'opened_at', 'closed_at', 'closing_note'];
    protected $casts = ['opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'expected_balance' => 'decimal:2', 'variance' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function transactions(): HasMany { return $this->hasMany(CashTransaction::class); }
}
