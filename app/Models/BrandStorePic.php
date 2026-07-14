<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandStorePic extends Model
{
    protected $table = 'brand_store_user';

    public $timestamps = false;

    protected $fillable = ['brand_id', 'store_id', 'user_id'];

    public function brand() { return $this->belongsTo(Brand::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function user()  { return $this->belongsTo(User::class); }
}
