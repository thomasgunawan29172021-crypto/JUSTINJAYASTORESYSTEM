<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\ServiceTicket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Semua KPI dihitung dari ticket_status_histories + timestamp di tiket.
 * Perhitungan dilakukan di PHP (bukan SQL join rumit) — dengan volume servis
 * saat ini ini lebih dari cukup dan jauh lebih mudah dimodifikasi.
 * Kalau nanti volume > ribuan tiket/bulan, pindahkan agregasi ke SQL.
 */
class KpiService
{
    /**
     * @return array{
     *   summary: array, technicians: Collection, admins: Collection,
     *   backlog: Collection, notPickedUp: Collection
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): array
    {
        $base = ServiceTicket::query()
            ->with(['histories', 'technician', 'admin', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        // Tiket yang tersentuh periode ini (masuk ATAU keluar dalam periode)
        $tickets = (clone $base)
            ->where(fn ($q) => $q
                ->whereBetween('checked_in_at', [$from, $to])
                ->orWhereBetween('checked_out_at', [$from, $to]))
            ->get();

        $in       = $tickets->filter(fn ($t) => $t->checked_in_at->between($from, $to));
        $out      = $tickets->filter(fn ($t) => $t->checked_out_at?->between($from, $to));
        $repaired = $out->filter(fn ($t) => $t->approved_cost !== null);
        $canceled = $out->filter(fn ($t) => $t->approved_cost === null);

        $openTickets = (clone $base)
            ->where('status', '!=', TicketStatus::Selesai->value)
            ->get();

        return [
            'summary'     => $this->summary($in, $out, $repaired, $canceled, $openTickets),
            'technicians' => $this->technicianKpi($tickets, $from, $to),
            'admins'      => $this->adminKpi($tickets),
            'backlog'     => $openTickets
                ->filter(fn ($t) => $t->ageDays() >= 7)
                ->sortByDesc(fn ($t) => $t->ageDays())
                ->values(),
            'notPickedUp' => $openTickets
                ->filter(fn ($t) => $t->status === TicketStatus::SiapDiambil
                    && $t->completed_at?->lt(now()->subDays(3)))
                ->sortBy('completed_at')
                ->values(),
        ];
    }

    protected function summary(Collection $in, Collection $out, Collection $repaired, Collection $canceled, Collection $open): array
    {
        return [
            'tickets_in'        => $in->count(),
            'tickets_out'       => $out->count(),
            'open_now'          => $open->count(),
            // diffInMinutes dipakai (bukan floatDiffInDays) supaya kompatibel Carbon 2 & 3
            'avg_tat_days'      => round($repaired->avg(
                fn ($t) => $t->checked_in_at->diffInMinutes($t->checked_out_at) / 1440
            ) ?? 0, 1),
            'cancel_rate'       => $out->count() > 0
                ? round($canceled->count() / $out->count() * 100)
                : 0,
            'service_revenue'   => (int) $repaired->sum('final_cost'),
            'parts_cost'        => (int) $repaired->sum(fn ($t) => $t->partsCost()),
            'avg_notify_lag_min' => round($out
                ->filter(fn ($t) => $t->completed_at && $t->notified_at)
                ->avg(fn ($t) => $t->completed_at->diffInMinutes($t->notified_at)) ?? 0),
        ];
    }

    /**
     * KPI per teknisi:
     * - unit selesai (masuk QC lolos → siap_diambil dalam periode)
     * - rata-rata durasi diagnosa (diterima→diagnosa selesai) & pengerjaan (jam)
     * - first-time-fix rate: % unit yang TIDAK balik sebagai klaim garansi ≤ warranty_days
     */
    protected function technicianKpi(Collection $tickets, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $tickets
            ->filter(fn ($t) => $t->technician_id !== null)
            ->groupBy('technician_id')
            ->map(function (Collection $group) use ($from, $to) {
                $done = $group->filter(function ($t) use ($from, $to) {
                    $ready = $this->enteredAt($t, TicketStatus::SiapDiambil);

                    return $ready && $ready->between($from, $to) && $t->approved_cost !== null;
                });

                $claims = $done->filter(fn ($t) => $t->warrantyClaims()
                    ->where('created_at', '<=', now())
                    ->exists());

                return [
                    'name'               => $group->first()->technician?->name ?? '—',
                    'done'               => $done->count(),
                    'avg_diagnosa_hours' => $this->avgDurationHours($group, TicketStatus::Diagnosa),
                    'avg_work_hours'     => $this->avgDurationHours($group, TicketStatus::Dikerjakan),
                    'qc_fail'            => $group->sum(fn ($t) => $t->histories
                        ->where('from_status', TicketStatus::Qc)
                        ->where('to_status', TicketStatus::Dikerjakan)
                        ->count()),
                    'ftf_rate'           => $done->count() > 0
                        ? round((1 - $claims->count() / $done->count()) * 100)
                        : null,
                ];
            })
            ->sortByDesc('done')
            ->values();
    }

    /**
     * KPI per admin chat:
     * - jeda "selesai QC" → customer dikabari (menit) — makin kecil makin baik
     * - konversi konfirmasi: % estimasi yang disetujui vs dibatalkan
     */
    protected function adminKpi(Collection $tickets): Collection
    {
        return $tickets
            ->filter(fn ($t) => $t->admin_id !== null)
            ->groupBy('admin_id')
            ->map(function (Collection $group) {
                $confirmed = $group->filter(fn ($t) => $t->histories
                    ->contains(fn ($h) => $h->from_status === TicketStatus::MenungguKonfirmasi));

                $approved = $confirmed->filter(fn ($t) => $t->histories
                    ->contains(fn ($h) => $h->from_status === TicketStatus::MenungguKonfirmasi
                        && $h->to_status !== TicketStatus::Dibatalkan));

                $notified = $group->filter(fn ($t) => $t->completed_at && $t->notified_at);

                return [
                    'name'            => $group->first()->admin?->name ?? '—',
                    'handled'         => $group->count(),
                    'approval_rate'   => $confirmed->count() > 0
                        ? round($approved->count() / $confirmed->count() * 100)
                        : null,
                    'avg_notify_min'  => $notified->count() > 0
                        ? round($notified->avg(fn ($t) => $t->completed_at->diffInMinutes($t->notified_at)))
                        : null,
                ];
            })
            ->sortByDesc('handled')
            ->values();
    }

    /** Rata-rata lama tiket berada DI DALAM satu status (jam). */
    protected function avgDurationHours(Collection $tickets, TicketStatus $status): ?float
    {
        $durations = [];

        foreach ($tickets as $ticket) {
            $histories = $ticket->histories->values();

            foreach ($histories as $i => $h) {
                if ($h->to_status !== $status) {
                    continue;
                }
                $exit = $histories->slice($i + 1)
                    ->first(fn ($next) => $next->from_status === $status);

                if ($exit) {
                    $durations[] = $h->created_at->diffInMinutes($exit->created_at) / 60;
                }
            }
        }

        return $durations === []
            ? null
            : round(array_sum($durations) / count($durations), 1);
    }

    /** Waktu tiket pertama kali masuk ke suatu status. */
    protected function enteredAt(ServiceTicket $ticket, TicketStatus $status): ?CarbonInterface
    {
        return $ticket->histories->firstWhere('to_status', $status)?->created_at;
    }
}
