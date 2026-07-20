<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarrantyVendor extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'note'];

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class, 'vendor_id');
    }
}
