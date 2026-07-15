<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'marketplace', 'is_mall', 'is_active', 'account_email', 'account_phone', 'account_password'];

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

    public function label(): string
    {
        return $this->name.' — '.ucfirst($this->marketplace).($this->is_mall ? ' Mall' : '');
    }
}
