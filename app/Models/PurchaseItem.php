<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['product_name', 'product_key', 'brand', 'product_type', 'quantity', 'unit_price', 'subtotal'];

    protected $casts = ['unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
}
