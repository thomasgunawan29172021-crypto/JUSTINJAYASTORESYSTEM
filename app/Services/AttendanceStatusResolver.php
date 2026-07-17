<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonInterface;

class AttendanceStatusResolver
{
    public const HADIR           = 'hadir';
    public const TELAT           = 'telat';
    public const OFF             = 'off';
    public const LIBUR_NASIONAL  = 'libur_nasional';
    public const DIBAYAR         = 'dibayar';         // sakit / cuti / izin-override — approved
    public const IZIN_DIPOTONG   = 'izin_dipotong';   // izin approved, is_paid=false
    public const MENUNGGU        = 'menunggu';        // leave masih pending, belum lewat/lewat tapi masih tunggu keputusan
    public const ALPHA           = 'alpha';            // tidak hadir, tidak ada leave sah

    /** Tentukan status 1 orang di 1 tanggal. Tanggal masa depan tidak dievaluasi (return null). */
    public function resolve(User $user, CarbonInterface $date): ?string
    {
        if ($date->isFuture()) {
            return null;
        }

        // 1. Libur nasional menang atas segalanya — dibayar tanpa syarat hadir
        if (Holiday::where('date', $date->toDateString())->exists()) {
            return self::LIBUR_NASIONAL;
        }

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $date)->first();

        // 2. Ada leave yang cover tanggal ini?
        //
        // whereIn type WAJIB: ganti jadwal BUKAN ketidakhadiran — stafnya tetap masuk.
        // Tanpa filter ini, step 2 balik DIBAYAR dan step 3 (cek hadir) gak pernah
        // kejalan, jadi orang yang beneran kerja kecatat "Dibayar (leave)".
        // Gajinya aman, tapi rekapnya bohong.
        $leave = LeaveRequest::where('user_id', $user->id)
            ->whereIn('type', LeaveType::absenceValues())
            ->whereNotIn('status', [LeaveStatus::Rejected->value, LeaveStatus::Expired->value])
            ->where('date_from', '<=', $date)
            ->where('date_to', '>=', $date)
            ->latest('id')
            ->first();

        if ($leave) {
            if ($leave->status === LeaveStatus::Pending) {
                return self::MENUNGGU;
            }
            // Approved
            return $leave->is_paid ? self::DIBAYAR : self::IZIN_DIPOTONG;
        }

        // 3. Hadir?
        if ($attendance) {
            return $attendance->isLate() ? self::TELAT : self::HADIR;
        }

        // 4. Penilaian off/alpha butuh konteks jadwal. Tanpa jadwal, atau
        //    tanggal sebelum effective_from → tidak dievaluasi (bukan alpha).
        $schedule = $user->workSchedule;

        if (! $schedule || ($schedule->effective_from && $date->lt($schedule->effective_from))) {
            return null;
        }

        if ($schedule->isOffDay($date->dayOfWeek)) {
            return self::OFF;
        }

        // 5. Tidak ada apa-apa yang membenarkan ketidakhadiran → alpha
        return self::ALPHA;
    }

    /**
     * Versi batch dari resolve(): logika IDENTIK, tapi nol query —
     * attendance, leaves, dan holidays dioper dari luar (prefetch).
     * $leaves HARUS sudah: difilter status (bukan rejected/expired) + terurut id DESC.
     * $holidayDates = array tanggal 'Y-m-d'.
     */
    public function resolveFromContext(
        User $user,
        CarbonInterface $date,
        ?Attendance $attendance,
        \Illuminate\Support\Collection $leaves,
        array $holidayDates
    ): ?string {
        if ($date->isFuture()) {
            return null;
        }

        // 1. Libur nasional menang atas segalanya
        if (in_array($date->toDateString(), $holidayDates, true)) {
            return self::LIBUR_NASIONAL;
        }

        // 2. Leave yang cover tanggal ini (list sudah urut id desc → first = latest id)
        //
        // isAbsence() difilter DI SINI, bukan diserahin ke pemanggil: kalau pemanggil
        // lupa, orang yang ganti jadwal kecatat "Dibayar" padahal masuk — dan gagalnya
        // senyap. Lebih murah ngecek dua kali daripada ketauan sebulan kemudian.
        $leave = $leaves->first(
            fn ($l) => $l->type->isAbsence() && $l->date_from->lte($date) && $l->date_to->gte($date)
        );

        if ($leave) {
            if ($leave->status === LeaveStatus::Pending) {
                return self::MENUNGGU;
            }
            return $leave->is_paid ? self::DIBAYAR : self::IZIN_DIPOTONG;
        }

        // 3. Hadir?
        if ($attendance) {
            return $attendance->isLate() ? self::TELAT : self::HADIR;
        }

        // 4. Off/alpha butuh jadwal
        $schedule = $user->workSchedule;

        if (! $schedule || ($schedule->effective_from && $date->lt($schedule->effective_from))) {
            return null;
        }

        if ($schedule->isOffDay($date->dayOfWeek)) {
            return self::OFF;
        }
        
        return self::ALPHA;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::HADIR          => 'Hadir',
            self::TELAT          => 'Telat',
            self::OFF            => 'Off',
            self::LIBUR_NASIONAL => 'Libur Nasional',
            self::DIBAYAR        => 'Dibayar (leave)',
            self::IZIN_DIPOTONG  => 'Izin (dipotong)',
            self::MENUNGGU       => 'Menunggu Keputusan',
            self::ALPHA          => 'Alpha',
            default              => '?',
        };
    }

    /** Status yang memotong gaji harian. */
    public static function isDeducted(string $status): bool
    {
        return in_array($status, [self::IZIN_DIPOTONG, self::ALPHA], true);
    }
}