<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** Toleransi telat (menit) — kebijakan Thomas. Ubah di sini, bukan di data. */
    public const LATE_TOLERANCE_MIN = 5;

    protected $fillable = [
        'user_id', 'branch_id', 'work_date',
        'clock_in_at', 'clock_in_lat', 'clock_in_lng', 'clock_in_distance_m', 'clock_in_photo',
        'late_minutes',
        'clock_out_at', 'clock_out_lat', 'clock_out_lng', 'clock_out_distance_m', 'clock_out_photo',
        'auto_closed', 'is_off_day',
        'retake_in_requested', 'retake_out_requested', 'retake_reason',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
        'auto_closed'  => 'boolean',
        'is_off_day'   => 'boolean',
        'retake_in_requested'  => 'boolean',
        'retake_out_requested' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Cabang aktual tempat karyawan clock-in pada hari tersebut. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function corrections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceCorrection::class)->orderByDesc('created_at');
    }

    public function isLate(): bool
    {
        return $this->late_minutes > self::LATE_TOLERANCE_MIN;
    }

    /** Total menit kerja; null kalau belum clock-out. */
    public function workedMinutes(): ?int
    {
        return $this->clock_out_at
            ? (int) $this->clock_in_at->diffInMinutes($this->clock_out_at)
            : null;
    }
}
