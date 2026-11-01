<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveItem extends Model
{
    protected $fillable = ['product_name', 'ram', 'color', 'condition', 'imei', 'capital_price', 'sale_price', 'sort_order'];
    protected function casts(): array { return ['capital_price' => 'decimal:2', 'sale_price' => 'decimal:2']; }
    public function submission(): BelongsTo { return $this->belongsTo(IncentiveSubmission::class, 'incentive_submission_id'); }
}
