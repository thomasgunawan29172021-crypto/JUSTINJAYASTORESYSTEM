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
        'name', 'barcode', 'sku', 'brand_id', 'category_id', 'cost_price',
        'program_discount_percent', 'price_offline', 'price_grosir',
        'archived_at', 'replacement_product_id',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        // 'float' (bukan 'decimal:2') supaya null tetap null dan aritmetika di
        // calculator gak kejebak string. Lihat catatan di MarketplaceCategoryFee.
        'program_discount_percent' => 'float',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    /**
     * Diskon program yang berlaku (%): override produk kalau ada, kalau tidak ikut brand.
     *
     * PENTING — null ≠ 0 di sini:
     *   null → produk ini gak punya pendapat, ikut default brand
     *   0    → produk ini MEMANG gak dapet program, walau brand-nya dapet
     * Jangan pernah tulis ($this->program_discount_percent ?: $brand) — operator `?:`
     * nganggep 0 itu kosong dan bakal diem-diem ngasih diskon brand ke produk yang
     * sengaja diset nol. Harus `??`, yang cuma nangkep null.
     */
    public function effectiveProgramDiscount(): float
    {
        return $this->program_discount_percent
            ?? $this->brand?->program_discount_percent
            ?? 0.0;
    }

    /** Modal setelah dipotong program — ini "M" di rumus pricing. */
    public function costAfterProgram(): float
    {
        return $this->cost_price * (1 - $this->effectiveProgramDiscount() / 100);
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
