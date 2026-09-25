<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PurchaseHistory extends Model {public $timestamps=false;protected $fillable=['user_id','action','changes','note','created_at'];protected $casts=['changes'=>'array','created_at'=>'datetime'];public function purchase():BelongsTo{return $this->belongsTo(Purchase::class);}public function user():BelongsTo{return $this->belongsTo(User::class);}}
