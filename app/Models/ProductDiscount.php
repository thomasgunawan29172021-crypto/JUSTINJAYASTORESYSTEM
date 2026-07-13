<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDiscount extends Model
{
    protected $fillable = ['product_id', 'name', 'starts_at', 'ends_at', 'note'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast() && ! $this->ends_at->isToday();
    }

    public function endsSoon(int $days = 30): bool
    {
        return ! $this->hasEnded() && $this->ends_at->lte(now()->addDays($days));
    }
}
