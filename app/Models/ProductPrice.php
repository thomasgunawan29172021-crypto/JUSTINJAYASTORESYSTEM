<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'marketplace', 'price_mall', 'price_regular'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
