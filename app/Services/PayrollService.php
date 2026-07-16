<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class PayrollService
{
    public function __construct(protected AttendanceStatusResolver $resolver) {}

    /**
     * Hitung draft slip 1 karyawan untuk 1 periode (belum disimpan).
     * @throws RuntimeException kalau ada blocker (pending leave / data belum siap)
     */
    public function calculate(User $user, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        if ($end->isFuture()) {
            throw new RuntimeException('Periode belum berakhir — slip hanya bisa dibuat untuk bulan yang sudah selesai.');
        }

        if ($user->base_salary <= 0) {
            throw new RuntimeException("Gaji pokok {$user->name} belum diset CEO (User Management).");
        }

        $schedule = $user->workSchedule;
        if (! $schedule) {
            throw new RuntimeException("Jadwal kerja {$user->name} belum diatur CEO.");
        }

        // BLOCKER (keputusan Thomas, opsi A): masih ada pengajuan menunggu yang
        // menyentuh bulan ini → slip tidak boleh terbit sampai semuanya diputuskan.
        $pendingCount = LeaveRequest::where('user_id', $user->id)
            ->where('status', LeaveStatus::Pending->value)
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->count();

        if ($pendingCount > 0) {
            throw new RuntimeException(
                "{$user->name} punya {$pendingCount} pengajuan izin/cuti yang belum diputuskan CEO di periode ini. Putuskan dulu di Approval Izin."
            );
        }

        // Pembagi (keputusan Thomas, opsi b — dinamis):
        // hari kalender bulan itu − jumlah hari off terjadwal di bulan itu
        $offDays = 0;
        $statuses = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($schedule->isOffDay($d->dayOfWeek)) {
                $offDays++;
            }
            $statuses[$d->toDateString()] = $this->resolver->resolve($user, $d->copy());
        }

        $workdays = $start->daysInMonth - $offDays;

        $deductedDays = collect($statuses)
            ->filter(fn ($s) => $s !== null && AttendanceStatusResolver::isDeducted($s))
            ->count();

        $dailyRate = (int) round($user->base_salary / max($workdays, 1));
        $deduction = $dailyRate * $deductedDays;

        return [
            'user_id'          => $user->id,
            'period'           => $period,
            'base_salary'      => $user->base_salary,
            'workdays'         => $workdays,
            'daily_rate'       => $dailyRate,
            'deducted_days'    => $deductedDays,
            'deduction_amount' => $deduction,
            'net_salary'       => max($user->base_salary - $deduction, 0),
            'day_statuses'     => $statuses,
        ];
    }

    /** Terbitkan (simpan snapshot). Gagal kalau slip periode itu sudah ada. */
    public function issue(User $user, string $period, User $issuer): Payslip
    {
        if (Payslip::where('user_id', $user->id)->where('period', $period)->exists()) {
            throw new RuntimeException("Slip {$user->name} periode {$period} sudah terbit — snapshot tidak boleh ditimpa.");
        }

        return Payslip::create($this->calculate($user, $period) + [
            'issued_by' => $issuer->id,
            'issued_at' => now(),
        ]);
    }
}