<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    
    public function pics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user');
    }

    /** PIC per toko — sumber kebenaran baru. brand_user = turunan dari ini. */
    public function storePics(): HasMany
    {
        return $this->hasMany(BrandStorePic::class);
    }
}
