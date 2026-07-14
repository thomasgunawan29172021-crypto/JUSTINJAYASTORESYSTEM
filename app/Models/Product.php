<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'barcode', 'sku', 'brand_id', 'cost_price', 'price_offline', 'price_grosir',
        'archived_at', 'replacement_product_id',
    ];

    protected $casts = ['archived_at' => 'datetime'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /** Toko yang SUDAH memposting produk ini (sumber kebenaran status posting). */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_product_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Harga yang berlaku untuk sebuah toko (mall/non-mall) — dipakai M2. */
    public function priceForStore(Store $store): ?int
    {
        $row = $this->prices->firstWhere('marketplace', $store->marketplace);

        if (! $row) {
            return null;
        }

        return $store->is_mall ? $row->price_mall : $row->price_regular;
    }
}
