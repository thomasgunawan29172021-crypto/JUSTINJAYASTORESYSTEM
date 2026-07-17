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
        'user_id', 'type', 'date_from', 'date_to', 'start_time', 'end_time',
        'reason', 'attachment_path',
        'status', 'is_paid', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'type'       => LeaveType::class,
        'status'     => LeaveStatus::class,
        'date_from'  => 'date',
        'date_to'    => 'date',
        'is_paid'    => 'boolean',
        'decided_at' => 'datetime',
        // start_time & end_time SENGAJA gak di-cast: 'datetime' bakal nempelin
        // tanggal hari ini ke jam doang, dan itu bikin banding jam jadi ngaco.
        // Biarin string 'HH:MM:SS' — sama kayak work_schedule_days.clock_in_time.
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

    /**
     * Ada pengajuan lain (pending/approved) yang tanggalnya tumpang tindih?
     *
     * Ganti jadwal SENGAJA dipisah: dia bukan ketidakhadiran, jadi boleh barengan
     * sama izin/sakit/cuti. Tanpa pemisahan ini staf bisa kekunci — ganti jadwal
     * Senin–Rabu disetujui, Selasa dia sakit, mau ajuin sakit malah ditolak
     * "bentrok", dan dia gak bisa batalin ganti jadwal yang udah disetujui.
     * Kalau dua-duanya kena satu hari, izinnya yang menang (resolver ngecek
     * ketidakhadiran duluan).
     */
    public static function overlapExists(int $userId, string $from, string $to, ?LeaveType $type = null): bool
    {
        return self::where('user_id', $userId)
            ->whereNotIn('status', [LeaveStatus::Rejected->value, LeaveStatus::Expired->value])
            ->when(
                $type === LeaveType::GantiJadwal,
                // ganti jadwal cuma bentrok sama sesama ganti jadwal
                fn ($q) => $q->where('type', LeaveType::GantiJadwal->value),
                // sisanya cuma bentrok sama sesama ketidakhadiran
                fn ($q) => $q->whereIn('type', LeaveType::absenceValues())
            )
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from)
            ->exists();
    }

    /**
     * Ganti jadwal yang DISETUJUI dan nyakup tanggal ini — null kalau gak ada.
     *
     * Dipakai AttendanceController pas clock-in (nentuin telat) dan auto-close
     * (nentuin jam pulang). Jamnya SATU buat seluruh rentang: kalau jadwal staf
     * beda-beda per hari (Senin 9–5, Sabtu 9–1), rentang yang kena dua-duanya
     * ikut diseragamin. Konsekuensi sadar dari "boleh rentang" + "jam manual".
     */
    public static function scheduleOverrideFor(int $userId, \Carbon\CarbonInterface $date): ?self
    {
        return self::where('user_id', $userId)
            ->where('type', LeaveType::GantiJadwal->value)
            ->where('status', LeaveStatus::Approved->value)
            ->where('date_from', '<=', $date->toDateString())
            ->where('date_to', '>=', $date->toDateString())
            ->latest('id')
            ->first();
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