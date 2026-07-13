<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTask extends Model
{
    public const TYPE_POSTING      = 'posting';
    public const TYPE_PRICE_UPDATE = 'price_update';
    public const TYPE_REVISION     = 'revision';

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE    = 'done';

    public $timestamps = false;

    protected $fillable = [
        'type', 'product_id', 'store_id', 'status', 'note', 'pinned_at',
        'created_at', 'completed_by', 'completed_at',
    ];

    protected $casts = ['created_at' => 'datetime', 'completed_at' => 'datetime', 'pinned_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_POSTING      => 'Posting baru',
            self::TYPE_PRICE_UPDATE => 'Update harga',
            self::TYPE_REVISION     => 'Revisi posting',
            default                 => $this->type,
        };
    }
}
