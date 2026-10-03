<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    public const TYPES = [
        'after_7_days' => '7 hari setelah pembelian',
        'after_1_month' => '1 bulan setelah pembelian',
        'after_6_months' => '6 bulan setelah pembelian',
        'after_1_year' => '1 tahun setelah pembelian',
    ];

    public const STATUSES = ['pending' => 'Belum dihubungi', 'completed' => 'Sudah dihubungi', 'skipped' => 'Dilewati'];

    protected $fillable = ['customer_id', 'purchase_id', 'branch_id', 'type', 'scheduled_at', 'status', 'completed_by', 'completed_at', 'note'];

    protected $casts = ['scheduled_at' => 'date', 'completed_at' => 'datetime'];

    public function customer() { return $this->belongsTo(Customer::class)->withTrashed(); }
    public function purchase() { return $this->belongsTo(Purchase::class)->withTrashed(); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function completer() { return $this->belongsTo(User::class, 'completed_by'); }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isCeo() && ! $user->hasRole(UserRole::Crm)) {
            $query->where('branch_id', $user->branch_id);
        }
    }
}
