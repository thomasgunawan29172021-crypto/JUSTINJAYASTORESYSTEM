<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biaya admin + ongkir untuk satu kombinasi (marketplace × tier toko × kategori).
 *
 * marketplace & tier disimpan sebagai string, nilainya DISALIN dari kolom yang sama
 * di `stores` — bukan diketik ulang. Jangan pernah bikin form yang nyuruh orang
 * ngetik nama marketplace di sini; sekali beda huruf, lookup-nya meleset diam-diam.
 */
class MarketplaceCategoryFee extends Model
{
    protected $fillable = [
        'marketplace', 'tier', 'category_id', 'admin_percent', 'shipping_cost',
    ];

    /**
     * 'float', BUKAN 'decimal:2' — cast decimal Laravel ngembaliin STRING, dan itu
     * bikin pengecekan di calculator jadi rawan. Nilai di sini paling banter 2 angka
     * di belakang koma (mis. 7.50), jauh dari batas presisi float.
     *
     * null tetap null (cast di-skip untuk null) — dan itu WAJIB dipertahankan:
     * null = "Thomas belum ngisi", 0 = "memang gratis". Dua hal beda.
     */
    protected $casts = [
        'admin_percent' => 'float',
        'shipping_cost' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Baris ini siap dipakai hitung? Setengah keisi = belum siap. */
    public function isComplete(): bool
    {
        return $this->admin_percent !== null && $this->shipping_cost !== null;
    }
}
