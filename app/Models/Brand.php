<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    /**
     * program_discount_percent = default diskon/subsidi supplier untuk brand ini
     * (mis. Robot kasih 10%). Produk bisa override sendiri — lihat
     * Product::effectiveProgramDiscount().
     */
    protected $fillable = ['name', 'program_discount_percent'];

    protected $casts = [
        'program_discount_percent' => 'float',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user');
    }

    /** PIC per toko — sumber kebenaran baru. brand_user = turunan dari ini. */
    public function storePics(): HasMany
    {
        return $this->hasMany(BrandStorePic::class);
    }
}
