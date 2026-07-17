<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    /**
     * Saran tier buat datalist form — BUKAN daftar tertutup.
     * Sengaja gak dibikin enum: tiap marketplace punya istilah tier sendiri,
     * jadi Thomas harus bisa nambah tier baru tanpa nunggu developer.
     */
    public const TIER_SUGGESTIONS = ['biasa', 'star', 'mall'];

    /**
     * UTANG TEKNIS — `tier` = SUMBER KEBENARAN. `is_mall` = DEPRECATED, cuma hidup
     * karena product_prices (price_mall/price_regular) & priceForStore() masih
     * gantung ke situ. is_mall DITURUNKAN dari tier di StoreController::validated().
     * FASE 2: restructure product_prices ke baris-per-tier, lalu buang is_mall.
     */
    protected $fillable = [
        'name', 'marketplace', 'tier', 'is_mall', 'is_active',
        'account_email', 'account_phone', 'account_password',
    ];

    protected $casts = [
        'is_mall'          => 'boolean',
        'is_active'        => 'boolean',
        'account_password' => 'encrypted',
    ];

    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    /**
     * Label tampilan, mis. "JJ Official — Shopee Mall".
     * Pakai `tier` (bukan is_mall) supaya tier selain mall — Star, dst — ikut kelihatan.
     * 'biasa' sengaja gak ditulis. Untuk data sekarang hasilnya identik versi lama.
     */
    public function label(): string
    {
        $suffix = $this->tier && $this->tier !== 'biasa'
            ? ' '.ucfirst($this->tier)
            : '';

        return $this->name.' — '.ucfirst($this->marketplace).$suffix;
    }
}