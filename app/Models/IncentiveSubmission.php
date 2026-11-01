<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncentiveSubmission extends Model
{
    public const CONDITIONS = ['new' => 'Baru', 'second' => 'Second', 'service' => 'Servis'];
    public const CURRENCIES = ['IDR' => 'Rupiah (IDR)', 'NTD' => 'New Taiwan Dollar (NTD)'];
    public const PLATFORMS = ['google' => 'Google Review', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'facebook' => 'Facebook'];

    protected $fillable = [
        'invoice_number', 'branch_id', 'transaction_date', 'currency', 'submitted_by',
        'approval_status', 'decision_by', 'decision_at', 'rejection_reason',
        'payment_status', 'payment_checked_by', 'payment_checked_at', 'is_cooperation',
        'total_capital', 'total_sales', 'total_profit', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date', 'decision_at' => 'datetime', 'payment_checked_at' => 'datetime',
            'is_cooperation' => 'boolean', 'total_capital' => 'decimal:2', 'total_sales' => 'decimal:2', 'total_profit' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decision_by'); }
    public function paymentChecker(): BelongsTo { return $this->belongsTo(User::class, 'payment_checked_by'); }
    public function items(): HasMany { return $this->hasMany(IncentiveItem::class); }
    public function participants(): HasMany { return $this->hasMany(IncentiveParticipant::class); }
    public function socialActions(): HasMany { return $this->hasMany(IncentiveSocialAction::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCeo() || $user->canReviewIncentivePayment()) return $query;

        return $query->where(fn (Builder $q) => $q->where('submitted_by', $user->id)
            ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id))
            ->orWhereHas('socialActions', fn (Builder $s) => $s->where('beneficiary_id', $user->id)));
    }
}
