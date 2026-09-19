<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerHistory extends Model
{
    public $timestamps = false; // cuma created_at, diisi manual

    protected $fillable = ['user_id', 'action', 'changes', 'note', 'created_at'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}