<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimPhoto extends Model
{
    public const TYPE_INTAKE   = 'intake';    // foto barang segala sisi, pas diterima
    public const TYPE_SHIPPING = 'shipping';  // bukti pengiriman / resi

    protected $fillable = ['claim_id', 'path', 'type', 'uploaded_by'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'claim_id');
    }
}
