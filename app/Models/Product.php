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
        'name', 'barcode', 'sku', 'brand_id', 'is_bundle', 'category_id', 'cost_price',
        'program_extra_percent', 'program_extra_amount',
        'price_offline', 'price_grosir', 'archived_at', 'replacement_product_id',
    ];

    protected $casts = [
        'archived_at'           => 'datetime',
        'is_bundle'             => 'boolean',
        'program_extra_percent' => 'float',
        'program_extra_amount'  => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Komponen bundle ini. Kosong kalau produk biasa. */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id');
    }

    /** Bundle mana aja yang MAKAI produk ini sebagai komponen. */
    public function usedInBundles(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'component_id');
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
     * Modal mentah SEBELUM program apa pun.
     * Bundle: total modal mentah komponen × qty — kolom cost_price-nya sendiri
     * diabaikan (buat bundle, kolom itu turunan, bukan input).
     *
     * Dipakai PricingCalculatorService buat ngecek "modal masih 0?" — pengecekan itu
     * gak boleh baca cost_price mentah, karena bundle selalu 0 di situ.
     */
    public function rawCost(): float
    {
        if (! $this->is_bundle) {
            return (float) $this->cost_price;
        }

        $this->loadMissing('bundleItems.component');

        return (float) $this->bundleItems->sum(
            fn (BundleItem $i) => ($i->component?->cost_price ?? 0) * $i->qty
        );
    }

    /**
     * Modal setelah semua potongan program — ini "M" di rumus pricing.
     *
     * PRODUK BIASA — 4 lapis BERTINGKAT (keputusan Thomas), tiap lapis dari SISA
     * lapis sebelumnya:
     *
     *   10.000 × 0,90 = 9.000     ← potong depan brand 10%
     *    9.000 × 0,95 = 8.550     ← potong belakang brand 5% (dari 9.000, bukan 10.000)
     *    8.550 × 0,98 = 8.379     ← tambahan produk 2%
     *    8.379 −  500 = 7.879     ← tambahan produk Rp 500
     *
     * Kalau dijumlah hasilnya 8.500 — beda, dan salah.
     * Nominal dipotong PALING AKHIR: potongan Rupiah gak kena persentase apa pun.
     *
     * BUNDLE — total modal komponen yang MASING-MASING udah kena program brand-nya
     * sendiri. Program brand bundle SENGAJA gak dipakai: kalau dipakai, program
     * kepotong DUA KALI (sekali di komponen, sekali di bundle) dan harganya jadi
     * kemurahan tanpa error apa pun. Bundle tetap boleh punya tambahan sendiri.
     *
     * Bisa balik NOL atau NEGATIF kalau potongannya kegedean. Sengaja gak di-clamp —
     * PricingCalculatorService yang nolak dengan pesan jelas. Kalau di-clamp diam-diam
     * ke 0, Thomas dapet harga jual ngaco tanpa tau kenapa.
     *
     * ⚠️ N+1: buat bundle, method ini butuh komponen + brand-nya. loadMissing() bikin
     * ini aman dipanggil sendirian, TAPI kalau dipanggil dalam loop WAJIB eager-load
     * dulu: ->with('bundleItems.component.brand')
     */
    public function costAfterProgram(): float
    {
        if ($this->is_bundle) {
            $this->loadMissing('bundleItems.component.brand');

            $cost = (float) $this->bundleItems->sum(
                fn (BundleItem $i) => ($i->component?->costAfterProgram() ?? 0) * $i->qty
            );
        } else {
            $this->loadMissing('brand');

            $cost = (float) $this->cost_price;
            $cost *= 1 - ($this->brand?->program_front_percent ?? 0) / 100;
            $cost *= 1 - ($this->brand?->program_back_percent ?? 0) / 100;
        }

        $cost *= 1 - ($this->program_extra_percent ?? 0) / 100;

        return $cost - (float) ($this->program_extra_amount ?? 0);
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
