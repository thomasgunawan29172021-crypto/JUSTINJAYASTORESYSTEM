<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'period', 'base_salary', 'workdays', 'daily_rate',
        'deducted_days', 'deduction_amount', 'net_salary',
        'day_statuses', 'issued_by', 'issued_at',
    ];

    protected $casts = [
        'day_statuses' => 'array',
        'issued_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}