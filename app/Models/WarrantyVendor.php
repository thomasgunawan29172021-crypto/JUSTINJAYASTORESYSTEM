<?php

namespace App\Models;

use App\Enums\WarrantyClaimFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarrantyVendor extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'note', 'requires_send_first'];

    protected $casts = [
        'requires_send_first' => 'boolean',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class, 'vendor_id');
    }

    /** Brand yang returnya diklaim ke vendor ini — penentu alur di form intake. */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'warranty_vendor_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(WarrantySupplierShipment::class, 'vendor_id');
    }

    /**
     * Alur DASAR buat brand vendor ini. Tukar-di-tempat tidak muncul di sini
     * karena itu keputusan per-transaksi (tergantung stok cabang saat itu),
     * bukan sifat vendornya.
     */
    public function defaultFlow(): WarrantyClaimFlow
    {
        return $this->requires_send_first
            ? WarrantyClaimFlow::KirimDulu
            : WarrantyClaimFlow::GantiDulu;
    }
}
