<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WaitingOrder extends Model
{
    protected $fillable = ['customer_id', 'created_by', 'notes'];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(WaitingItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isCeo() && ! $user->hasRole(UserRole::Crm)) {
            $query->whereHas('customer', fn ($q) => $q->where('branch_id', $user->branch_id));
        }
    }
}
