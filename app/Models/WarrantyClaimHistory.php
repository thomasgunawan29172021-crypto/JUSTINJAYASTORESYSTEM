<?php

namespace App\Models;

use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['claim_id', 'from_status', 'to_status', 'is_followup', 'user_id', 'note', 'created_at'];

    protected $casts = [
        'from_status' => WarrantyClaimStatus::class,
        'to_status'   => WarrantyClaimStatus::class,
        'is_followup' => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'claim_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
