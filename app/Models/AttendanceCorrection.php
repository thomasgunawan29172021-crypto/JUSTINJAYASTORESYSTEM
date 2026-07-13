<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = ['attendance_id', 'corrected_by', 'before', 'after', 'reason', 'created_at'];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
