<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori produk — master data yang dikelola CEO di halaman Pengaturan Harga.
 * Fungsinya cuma satu: nentuin biaya admin & ongkir mana yang dipakai sebuah produk.
 *
 * Sengaja TANPA SoftDeletes: products.category_id pakai restrictOnDelete, jadi
 * kategori yang masih dipakai memang gak boleh dihapus sama sekali — gak ada
 * kondisi "kehapus tapi masih nyangkut" yang perlu di-restore.
 */
class Category extends Model
{
    protected $fillable = ['name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(MarketplaceCategoryFee::class);
    }
}
