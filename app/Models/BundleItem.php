<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu komponen di dalam sebuah bundle. */
class BundleItem extends Model
{
    protected $fillable = ['bundle_id', 'component_id', 'qty'];

    protected $casts = ['qty' => 'integer'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_id');
    }

    /**
     * withTrashed() WAJIB: komponen bisa masuk Sampah sementara bundle-nya masih
     * hidup (restrictOnDelete cuma nahan hapus PERMANEN, bukan soft delete).
     * Tanpa ini, costAfterProgram() crash null di bundle yang komponennya di sampah.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id')->withTrashed();
    }
}
