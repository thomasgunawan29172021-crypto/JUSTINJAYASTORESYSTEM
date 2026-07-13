<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    public const DAYS = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    protected $fillable = ['user_id', 'clock_in_time', 'clock_out_time', 'off_day', 'effective_from'];

    protected $casts = ['effective_from' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offDayName(): string
    {
        return self::DAYS[$this->off_day] ?? '?';
    }
}