<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Services\AttendanceStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceRecapController extends Controller
{
    /** Rekap bulanan semua karyawan — khusus CEO. */
    public function index(Request $request, AttendanceStatusResolver $resolver)
    {
        $month = $this->month($request);

        $users = User::with(['branch', 'workSchedule'])
            ->where('is_active', true)
            ->when($request->filled('branch_id'),
                fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')))
            ->orderBy('name')->get();

        $ctx = $this->prefetch($users, $month);

        $rows = $users->map(fn ($u) => [
            'user'  => $u,
            'recap' => $this->summarize($this->buildDays($u, $month, $resolver, $ctx)),
        ]);

        return view('attendance.recap', [
            'rows'     => $rows,
            'month'    => $month,
            'branches' => Branch::all(),
        ]);
    }

    /** Detail per-hari 1 karyawan — khusus CEO. */
    public function show(Request $request, User $user, AttendanceStatusResolver $resolver)
    {
        $month = $this->month($request);
        $days  = $this->buildDays($user, $month, $resolver);

        return view('attendance.recap-detail', [
            'user'  => $user,
            'month' => $month,
            'days'  => $days,
            'recap' => $this->summarize($days),
            'self'  => false,
        ]);
    }

    /** Rekap milik sendiri — semua staf. */
    public function me(Request $request, AttendanceStatusResolver $resolver)
    {
        $month = $this->month($request);
        $user  = $request->user();
        $days  = $this->buildDays($user, $month, $resolver);

        return view('attendance.recap-detail', [
            'user'  => $user,
            'month' => $month,
            'days'  => $days,
            'recap' => $this->summarize($days),
            'self'  => true,
        ]);
    }

    /* ------------------------- Helper ------------------------- */

    protected function month(Request $request): Carbon
    {
        return $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
    }

    /** Status + record absen tiap tanggal — versi prefetch, nol query per hari. */
    protected function buildDays(User $user, Carbon $month, AttendanceStatusResolver $resolver, ?array $ctx = null): array
    {
        $ctx ??= $this->prefetch(collect([$user]), $month);

        $atts   = $ctx['attendances'][$user->id] ?? collect();
        $leaves = $ctx['leaves'][$user->id] ?? collect();

        $end  = $month->copy()->endOfMonth()->min(now()->endOfDay());
        $days = [];

        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $att = $atts[$d->toDateString()] ?? null;
            $days[] = [
                'date'       => $d->copy(),
                'status'     => $resolver->resolveFromContext($user, $d->copy(), $att, $leaves, $ctx['holidays']),
                'attendance' => $att,
            ];
        }

        return $days;
    }

    /** Ambil semua data sebulan untuk banyak user sekaligus: 3 query, bukan 3 per hari. */
    protected function prefetch(\Illuminate\Support\Collection $users, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $ids   = $users->pluck('id');

        return [
            'holidays' => \App\Models\Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all(),

            'attendances' => Attendance::with('corrections')
                ->whereIn('user_id', $ids)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->groupBy('user_id')
                ->map(fn ($g) => $g->keyBy(fn ($a) => $a->work_date->toDateString())),

            'leaves' => \App\Models\LeaveRequest::whereIn('user_id', $ids)
                ->whereNotIn('status', [\App\Enums\LeaveStatus::Rejected->value, \App\Enums\LeaveStatus::Expired->value])
                ->where('date_from', '<=', $end)
                ->where('date_to', '>=', $start)
                ->orderByDesc('id')
                ->get()
                ->groupBy('user_id'),
        ];
    }

    protected function summarize(array $days): array
    {
        $collection = collect($days);
        $atts       = $collection->pluck('attendance')->filter();

        return [
            'counts'        => $collection->pluck('status')->filter()->countBy(),
            'worked_hours'  => round($atts->sum(fn ($a) => $a->workedMinutes() ?? 0) / 60, 1),
            'late_minutes'  => (int) $atts->sum('late_minutes'),
            'deducted_days' => $collection
                ->filter(fn ($d) => $d['status'] && AttendanceStatusResolver::isDeducted($d['status']))
                ->count(),
        ];
    }
}