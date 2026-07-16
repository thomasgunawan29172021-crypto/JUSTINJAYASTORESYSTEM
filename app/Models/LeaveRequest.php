<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    /** Jatah cuti per tahun (hari) — default versi gw, Thomas bisa ubah kapan pun. */
    public const CUTI_QUOTA_DAYS = 12;

    protected $fillable = [
        'user_id', 'type', 'date_from', 'date_to', 'reason', 'attachment_path',
        'status', 'is_paid', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'type'       => LeaveType::class,
        'status'     => LeaveStatus::class,
        'date_from'  => 'date',
        'date_to'    => 'date',
        'is_paid'    => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Jumlah hari kalender, inklusif. Hari off mingguan di dalam rentang IKUT terhitung (keputusan default — lihat catatan). */
    public function days(): int
    {
        return (int) $this->date_from->diffInDays($this->date_to) + 1;
    }

    /** Ada pengajuan lain (pending/approved) yang tanggalnya tumpang tindih? */
    public static function overlapExists(int $userId, string $from, string $to): bool
    {
        return self::where('user_id', $userId)
            ->whereNotIn('status', [LeaveStatus::Rejected->value, LeaveStatus::Expired->value])
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from)
            ->exists();
    }

    /** Total hari cuti yang SUDAH DIPAKAI ATAU SEDANG MENUNGGU (reservasi kuota). */
    public static function cutiUsedDays(int $userId, int $year): int
    {
        return self::where('user_id', $userId)
            ->where('type', LeaveType::Cuti->value)
            ->whereIn('status', [LeaveStatus::Approved->value, LeaveStatus::Pending->value])
            ->whereYear('date_from', $year)
            ->get()
            ->sum(fn ($l) => $l->days());
    }

}