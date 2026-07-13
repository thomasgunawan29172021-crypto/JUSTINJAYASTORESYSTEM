<?php

namespace App\Http\Controllers\Sosmed;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SocialTarget;
use App\Models\SocialVideo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(now()->endOfDay());

        [$users, $videosByUser, $rows] = $this->buildRows($month, $end);

        $currentTarget = SocialTarget::forDate(now())?->video_count;

        $byBranch = $rows->groupBy(fn ($r) => $r['user']->branch?->name ?? 'Tanpa Cabang');

        /* ---------- Alerts ---------- */
        $alerts = collect();

        $dueCount = SocialVideo::active()
            ->where('published_at', '<=', now()->subDays(SocialVideo::DUE_DAYS)->toDateString())
            ->count();
        if ($dueCount > 0) {
            $alerts->push([
                'level' => $dueCount >= 20 ? 'red' : 'yellow',
                'msg'   => "{$dueCount} video jatuh tempo update final metrik.",
            ]);
        }

        // Kemarin masuk tapi setoran kurang dari target
        $yesterday = now()->subDay()->toDateString();
        $tY = SocialTarget::forDate(now()->subDay())?->video_count;
        if ($tY !== null) {
            $present   = Attendance::whereDate('work_date', $yesterday)->pluck('user_id');
            $vidCounts = \Illuminate\Support\Facades\DB::table('social_video_user as svu')
                ->join('social_videos as sv', 'sv.id', '=', 'svu.social_video_id')
                ->whereNull('sv.deleted_at')
                ->where('svu.is_pic', true)
                ->whereDate('sv.published_at', $yesterday)
                ->whereIn('svu.user_id', $present)
                ->selectRaw('svu.user_id, count(*) c')
                ->groupBy('svu.user_id')->pluck('c', 'user_id');
            $short = $users->whereIn('id', $present)
                ->filter(fn ($u) => ($vidCounts[$u->id] ?? 0) < $tY);
            if ($short->isNotEmpty()) {
                $alerts->push([
                    'level' => 'yellow',
                    'msg'   => "Kemarin di bawah target ({$tY}/hari): " . $short->pluck('name')->join(', ') . '.',
                ]);
            }
        }

        /* ---------- Data grafik multi-metrik (client pilih mana ditampilkan) ---------- */
        $chartLabels = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) $chartLabels[] = $d->toDateString();

        // Snapshot terakhir per video + tanggal tayang → agregasi metrik per tanggal per user.
        // Catatan: metrik (views/like/dst) ditaruh di TANGGAL TAYANG videonya, bukan tanggal snapshot —
        // biar sejajar dengan setoran. Ini "performa video yang tayang di tanggal itu".
        $chartColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];

        $metricDefs = [
            'videos'   => ['label' => 'Setoran', 'axis' => 'right', 'get' => fn ($v) => 1],
            // Konsistensi sengaja TIDAK diplot: itu agregat per-pegawai-per-bulan (% hari capai
            // target), bukan angka harian. Sudah tampil di kolom % tabel + leaderboard.
            // Metrik = gabungan semua platform tempat video itu tayang.
            'views'    => ['label' => 'Views', 'axis' => 'left', 'get' => fn ($v) => $v->metricTotal('views')],
            'likes'    => ['label' => 'Likes', 'axis' => 'left', 'get' => fn ($v) => $v->metricTotal('likes')],
            'comments' => ['label' => 'Komen', 'axis' => 'left', 'get' => fn ($v) => $v->metricTotal('comments')],
            'saves'    => ['label' => 'Save',  'axis' => 'left', 'get' => fn ($v) => $v->metricTotal('saves')],
        ];

        // Struktur: chartData[metric] = array of dataset (satu per pegawai)
        $chartData  = [];
        $activeRows = $rows->filter(fn ($r) => $r['videos'] > 0)->values();

        foreach (array_keys($metricDefs) as $metric) {
            $def = $metricDefs[$metric];
            $chartData[$metric] = $activeRows->map(function ($r, $i) use ($videosByUser, $chartLabels, $chartColors, $def) {
                $vids   = $videosByUser[$r['user']->id] ?? collect();
                $byDate = $vids->groupBy(fn ($v) => $v->published_at->toDateString())
                    ->map(fn ($g) => $g->sum($def['get']));
                return [
                    'label'           => $r['user']->name,
                    'data'            => collect($chartLabels)->map(fn ($d) => (int) ($byDate[$d] ?? 0)),
                    'borderColor'     => $chartColors[$i % count($chartColors)],
                    'backgroundColor' => $chartColors[$i % count($chartColors)],
                    'yAxisID'         => $def['axis'] === 'left' ? 'yBig' : 'ySmall',
                    'tension'         => 0.3,
                    'pointRadius'     => 2,
                ];
            })->values();
        }

        return view('sosmed.report', compact(
            'month', 'byBranch', 'alerts', 'currentTarget',
            'chartLabels', 'chartData'
        ));
    }

    /** Ubah target — CEO only. Riwayat tersimpan, tidak menimpa. */
    public function storeTarget(Request $request)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa mengubah target.');

        $data = $request->validate([
            'video_count'    => ['required', 'integer', 'min:1', 'max:100'],
            'effective_from' => ['required', 'date'],
        ]);

        SocialTarget::create([
            ...$data,
            'period'     => 'daily',
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return back()->with('ok', "Target baru {$data['video_count']} video/hari berlaku mulai {$data['effective_from']}.");
    }

    /** Leaderboard bulanan — terbuka semua staf. */
    public function leaderboard(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(now()->endOfDay());

        [, , $rows] = $this->buildRows($month, $end);

        // Terbaik: hanya yang aktif (punya video). Terburuk: semua yang MASUK kerja
        // di periode ini — 0 video justru harus kelihatan. Yang tak pernah masuk dikecualikan.
        $active  = $rows->filter(fn ($r) => $r['videos'] > 0);
        $present = $rows->filter(fn ($r) => $r['days_present'] > 0);

        return view('sosmed.leaderboard', [
            'month'  => $month,
            'boards' => [
                'videos' => [
                    'title' => '🎬 Setoran',
                    'best'  => $active->sortByDesc(fn ($r) => [$r['videos'], $r['views']])->values(),
                    'worst' => $present->sortBy(fn ($r) => [$r['videos'], $r['views']])->values(),
                ],
                'views' => [
                    'title' => '👀 Views',
                    'best'  => $active->sortByDesc(fn ($r) => [$r['views'], $r['videos']])->values(),
                    'worst' => $present->sortBy(fn ($r) => [$r['views'], $r['videos']])->values(),
                ],
                'met' => [
                    'title' => '🎯 Konsistensi',
                    'best'  => $active->filter(fn ($r) => $r['met_pct'] !== null)
                                      ->sortByDesc(fn ($r) => [$r['met_pct'], $r['videos']])->values(),
                    'worst' => $present->filter(fn ($r) => $r['met_pct'] !== null)
                                       ->sortBy(fn ($r) => [$r['met_pct'], $r['videos']])->values(),
                ],
            ],
            'me' => $request->user()->id,
        ]);
    }

    /** Hitungan baris per pegawai — dipakai laporan & leaderboard. */
    protected function buildRows(Carbon $month, Carbon $end): array
    {
        /* ---------- PREFETCH (pola anti-N+1) ---------- */
        $users = User::with('branch')->where('is_active', true)->orderBy('name')->get();

        $videos = SocialVideo::with(['creators:id', 'postings.latestSnapshot'])
            ->whereBetween('published_at', [$month->toDateString(), $end->toDateString()])
            ->get();

        // KPI hanya untuk PIC video — anggota colab nol kredit (keputusan Thomas)
        $videosByUser = collect();
        foreach ($videos as $v) {
            $picU = $v->creators->firstWhere('pivot.is_pic', true);
            if ($picU) {
                $videosByUser[$picU->id] = ($videosByUser[$picU->id] ?? collect())->push($v);
            }
        }

        $attendance = Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$month->toDateString(), $end->toDateString()])
            ->get(['user_id', 'work_date'])
            ->groupBy('user_id')
            ->map(fn ($g) => $g->pluck('work_date')->map(fn ($d) => $d->toDateString()));

        // Semua target yang bisa berlaku di bulan ini — resolve per tanggal di memory
        $targets = SocialTarget::where('effective_from', '<=', $end->toDateString())
            ->orderByDesc('effective_from')->orderByDesc('id')->get();
        $targetFor = function (string $date) use ($targets) {
            return $targets->first(fn ($t) => $t->effective_from->toDateString() <= $date)?->video_count;
        };

        /* ---------- Baris per pegawai ---------- */
        $rows = $users->map(function ($u) use ($videosByUser, $attendance, $targetFor) {
            $vids     = $videosByUser[$u->id] ?? collect();
            $byDate   = $vids->groupBy(fn ($v) => $v->published_at->toDateString())->map->count();
            $workDays = $attendance[$u->id] ?? collect();

            $daysMet = $workDays->filter(function ($d) use ($byDate, $targetFor) {
                $t = $targetFor($d);
                return $t !== null && ($byDate[$d] ?? 0) >= $t;
            })->count();

            // Metrik = gabungan semua platform per video, lalu dijumlah lintas video.
            $views = $vids->sum(fn ($v) => $v->metricTotal('views'));
            $likes = $vids->sum(fn ($v) => $v->metricTotal('likes'));

            return [
                'user'         => $u,
                'videos'       => $vids->count(),
                'days_present' => $workDays->count(),
                'avg_per_day'  => $workDays->count() > 0 ? round($vids->count() / $workDays->count(), 1) : null,
                'days_met'     => $daysMet,
                'met_pct'      => $workDays->count() > 0 ? (int) round($daysMet / $workDays->count() * 100) : null,
                'views'        => (int) $views,
                'likes'        => (int) $likes,
            ];
        });

        return [$users, $videosByUser, $rows];
    }
}