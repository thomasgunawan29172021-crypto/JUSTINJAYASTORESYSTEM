<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitingItem extends Model
{
    public const STATUSES = ['waiting' => 'Menunggu', 'notified' => 'Sudah dikabari', 'purchased' => 'Jadi beli'];

    protected $fillable = ['product_name', 'product_key', 'quantity', 'unit_price', 'status', 'notified_at', 'purchased_at'];

    protected $casts = ['unit_price' => 'decimal:2', 'notified_at' => 'datetime', 'purchased_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(WaitingOrder::class, 'waiting_order_id');
    }
}
