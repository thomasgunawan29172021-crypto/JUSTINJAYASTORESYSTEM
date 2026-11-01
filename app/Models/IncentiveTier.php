<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveTier extends Model
{
    public const TYPES = ['fl' => 'Promotor FL', 'brand' => 'Promotor Brand', 'social' => 'Rating / Follow'];
    protected $fillable = ['branch_id', 'currency', 'type', 'effective_from', 'label', 'min_target', 'reward_value'];
    protected function casts(): array { return ['effective_from' => 'date', 'min_target' => 'decimal:2', 'reward_value' => 'decimal:4']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
