<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PurchaseItem extends Model {protected $fillable=['product_id','product_name','imei','qty','unit_price','subtotal','warranty_type','warranty_until'];protected $casts=['warranty_until'=>'date'];public function purchase():BelongsTo{return $this->belongsTo(Purchase::class);}public function product():BelongsTo{return $this->belongsTo(Product::class);}}
