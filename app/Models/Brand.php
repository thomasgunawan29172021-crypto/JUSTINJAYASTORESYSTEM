<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    /**
     * Program supplier, BERTINGKAT (keputusan Thomas):
     *   10.000 −10% = 9.000, lalu −5% dari 9.000 = 8.550
     * Bukan dijumlah (yang bakal ngasih 8.500). Lihat Product::costAfterProgram().
     */
    protected $fillable = ['name', 'program_front_percent', 'program_back_percent', 'warranty_vendor_id'];

    protected $casts = [
        'program_front_percent' => 'float',
        'program_back_percent'  => 'float',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    /**
     * Vendor tujuan klaim retur. Dari sinilah sistem tahu barang brand ini
     * wajib dikirim dulu (Ugreen/Anker) atau boleh diganti duluan (Robot/Olike).
     * Diatur di halaman Vendor Retur, bukan di halaman Brand — yang paham
     * aturan main tiap distributor itu tim retur, bukan tim marketplace.
     */
    public function warrantyVendor(): BelongsTo
    {
        return $this->belongsTo(WarrantyVendor::class, 'warranty_vendor_id')->withTrashed();
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
