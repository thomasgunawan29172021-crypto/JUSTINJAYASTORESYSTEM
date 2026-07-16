<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\LeaveRequest;
use App\Models\MarketplaceTask;
use App\Models\Posting;
use App\Models\ServiceTicket;
use App\Models\SocialVideo;
use Illuminate\Http\Request;
use App\Models\User;
// use App\Models\VideoMetricSnapshot;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $isCeo = $user->role->isCeo();

        // Staf biasa: sapaan + reminder absen. Data komando hanya untuk CEO.
        if (! $isCeo) {
            $schedule = $user->workSchedule;
            $already  = \App\Models\Attendance::where('user_id', $user->id)
                ->whereDate('work_date', today())->exists();

            $todayDay = $schedule?->dayFor(now()->dayOfWeek);
            $reminder = null;

            if ($schedule && ! $already && $todayDay && $todayDay->clock_in_time
                && ! ($schedule->effective_from && $schedule->effective_from->gt(today()))) {
                $reminder = [
                    'clockInTime'  => $todayDay->clock_in_time,
                    'toleranceMin' => \App\Models\Attendance::LATE_TOLERANCE_MIN,
                ];
            }

            return view('dashboard', ['isCeo' => false, 'reminder' => $reminder]);
        }

        /* ---------- SERVICE (operasional, bukan finansial) ---------- */
        $openStatuses = [
            TicketStatus::Diterima->value, TicketStatus::Diagnosa->value,
            TicketStatus::MenungguKonfirmasi->value, TicketStatus::MenungguSparepart->value,
            TicketStatus::Dikerjakan->value, TicketStatus::Qc->value,
        ];

        $svcOpen       = ServiceTicket::whereIn('status', $openStatuses)->count();
        $svcMacet7     = ServiceTicket::whereIn('status', $openStatuses)
            ->where('checked_in_at', '<', now()->subDays(7))->count();
        $svcBelumKabar = ServiceTicket::where('status', TicketStatus::SiapDiambil->value)
            ->whereNull('notified_at')->count();
        $svcKonfirmasi = ServiceTicket::where('status', TicketStatus::MenungguKonfirmasi->value)->count();

        /* ---------- MARKETPLACE ---------- */
        $mpPosted   = Posting::count();
        $mpPending  = MarketplaceTask::where('status', MarketplaceTask::STATUS_PENDING)->count();
        $mpRevisi   = MarketplaceTask::where('status', MarketplaceTask::STATUS_PENDING)
            ->where('type', MarketplaceTask::TYPE_REVISION)->count();
        $mpPrice    = MarketplaceTask::where('status', MarketplaceTask::STATUS_PENDING)
            ->where('type', MarketplaceTask::TYPE_PRICE_UPDATE)->count();

        /* ---------- SOSMED ---------- */
        $smVideosBulan = SocialVideo::whereBetween('published_at',
            [now()->startOfMonth()->toDateString(), now()->toDateString()])->count();
        $smDue = SocialVideo::active()
            ->where('published_at', '<=', now()->subDays(SocialVideo::DUE_DAYS)->toDateString())->count();

        /* ---------- ALERT GABUNGAN (headline lintas modul, tiap baris nge-link ke dashboard aslinya) ---------- */
        $alerts = collect();

        if ($svcMacet7 > 0) {
            $alerts->push(['level' => 'red', 'modul' => 'Servis', 'route' => 'service.dashboard',
                'msg' => "{$svcMacet7} unit servis nginap ≥ 7 hari."]);
        }
        if ($svcBelumKabar > 0) {
            $alerts->push(['level' => $svcBelumKabar >= 3 ? 'red' : 'yellow', 'modul' => 'Servis', 'route' => 'service.dashboard',
                'msg' => "{$svcBelumKabar} unit selesai belum dikabari ke customer."]);
        }
        if ($mpRevisi > 0) {
            $alerts->push(['level' => $mpRevisi >= 5 ? 'red' : 'yellow', 'modul' => 'Marketplace', 'route' => 'marketplace.dashboard',
                'msg' => "{$mpRevisi} tugas revisi marketplace di antrian."]);
        }
        if ($smDue > 0) {
            $alerts->push(['level' => $smDue >= 20 ? 'red' : 'yellow', 'modul' => 'Sosmed', 'route' => 'sosmed.report',
                'msg' => "{$smDue} video sosmed jatuh tempo update metrik."]);
        }

        // Cuti/izin nunggak (dipindah dari view lama ke sini)
        $overdueLeaves = LeaveRequest::with('user')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subDays(3))
            ->get();

        // Semua pending (bukan cuma yang nunggak) — biar CEO sigap sejak awal.
        $pendingLeavesCount = LeaveRequest::where('status', 'pending')->count();

        /* ---------- CHART: 30 hari terakhir ---------- */
        $chartFrom = now()->subDays(29)->startOfDay();
        $chartLabels = [];
        for ($d = $chartFrom->copy(); $d->lte(now()); $d->addDay()) $chartLabels[] = $d->toDateString();
        $chartColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];

        // -- Marketplace: tugas posting selesai per hari per PIC --
        $mpDone = MarketplaceTask::where('type', MarketplaceTask::TYPE_POSTING)
            ->where('status', MarketplaceTask::STATUS_DONE)
            ->where('completed_at', '>=', $chartFrom)
            ->whereNotNull('completed_by')
            ->selectRaw('completed_by, DATE(completed_at) d, count(*) c')
            ->groupBy('completed_by', 'd')->get()
            ->groupBy('completed_by');

        $mpNames = User::whereIn('id', $mpDone->keys())->pluck('name', 'id');

        $chartMp = $mpDone->values()->map(function ($rows, $i) use ($mpNames, $mpDone, $chartLabels, $chartColors) {
            $userId = $mpDone->keys()[$i];
            $byDate = $rows->pluck('c', 'd');
            return [
                'label'           => $mpNames[$userId] ?? "User {$userId}",
                'data'            => collect($chartLabels)->map(fn ($d) => (int) ($byDate[$d] ?? 0)),
                'borderColor'     => $chartColors[$i % count($chartColors)],
                'backgroundColor' => $chartColors[$i % count($chartColors)],
                'tension'         => 0.3, 'pointRadius' => 2,
            ];
        })->values();

        // -- Sosmed: video tayang per hari per pegawai --
        $smVids = \Illuminate\Support\Facades\DB::table('social_video_user as svu')
            ->join('social_videos as sv', 'sv.id', '=', 'svu.social_video_id')
            ->whereNull('sv.deleted_at')
            ->where('svu.is_pic', true)
            ->where('sv.published_at', '>=', $chartFrom->toDateString())
            ->selectRaw('svu.user_id, sv.published_at d, count(*) c')
            ->groupBy('svu.user_id', 'd')->get()
            ->groupBy('user_id');

        $smNames = User::whereIn('id', $smVids->keys())->pluck('name', 'id');

        $chartSm = $smVids->values()->map(function ($rows, $i) use ($smNames, $smVids, $chartLabels, $chartColors) {
            $userId = $smVids->keys()[$i];
            $byDate = $rows->mapWithKeys(fn ($r) => [\Illuminate\Support\Carbon::parse($r->d)->toDateString() => $r->c]);
            return [
                'label'           => $smNames[$userId] ?? "User {$userId}",
                'data'            => collect($chartLabels)->map(fn ($d) => (int) ($byDate[$d] ?? 0)),
                'borderColor'     => $chartColors[$i % count($chartColors)],
                'backgroundColor' => $chartColors[$i % count($chartColors)],
                'tension'         => 0.3, 'pointRadius' => 2,
            ];
        })->values();

        return view('dashboard', [
            'isCeo'         => true,
            'alerts'        => $alerts,
            'overdueLeaves' => $overdueLeaves,
            'pendingLeavesCount' => $pendingLeavesCount,
            'kpi' => [
                'svcOpen' => $svcOpen, 'svcMacet7' => $svcMacet7,
                'svcBelumKabar' => $svcBelumKabar, 'svcKonfirmasi' => $svcKonfirmasi,
                'mpPosted' => $mpPosted, 'mpPending' => $mpPending, 'mpRevisi' => $mpRevisi, 'mpPrice' => $mpPrice,
                'smVideosBulan' => $smVideosBulan, 'smDue' => $smDue,
            ],
            'chartLabels' => $chartLabels,
            'chartMp'     => $chartMp,
            'chartSm'     => $chartSm,
        ]);
    }
}
