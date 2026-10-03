<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
        'debit' => 'Kartu debit',
        'credit_card' => 'Kartu kredit',
        'credit' => 'Kredit / cicilan',
        'other' => 'Lainnya',
    ];

    protected $fillable = ['customer_id', 'branch_id', 'created_by', 'purchased_at', 'payment_method', 'total_amount', 'notes'];

    protected $casts = ['purchased_at' => 'date', 'total_amount' => 'decimal:2'];

    public function customer() { return $this->belongsTo(Customer::class)->withTrashed(); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function reminders() { return $this->hasMany(Reminder::class); }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isCeo() && ! $user->hasRole(UserRole::Crm)) {
            $query->where('branch_id', $user->branch_id);
        }
    }
}
