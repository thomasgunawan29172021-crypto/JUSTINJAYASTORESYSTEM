<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceTask;
use App\Models\Posting;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketplaceDashboardController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        /* ---------- PREFETCH (fix N+1): ±6 query, sisanya dihitung di memory ---------- */
        // Semua produk (id+brand). archived ikut — kinerja PIC lama memang tak filter archived.
        $allProducts   = Product::get(['id', 'brand_id', 'archived_at']);
        $activeByBrand = $allProducts->whereNull('archived_at')->groupBy('brand_id')->map(fn ($g) => $g->pluck('id'));
        $allByBrand    = $allProducts->groupBy('brand_id')->map(fn ($g) => $g->pluck('id'));

        $postings     = Posting::get(['product_id', 'store_id', 'posted_by']);
        $pendingTasks = MarketplaceTask::where('status', MarketplaceTask::STATUS_PENDING)
            ->get(['type', 'product_id', 'store_id', 'created_at']);
        $doneTasks    = MarketplaceTask::where('status', MarketplaceTask::STATUS_DONE)
            ->whereBetween('completed_at', [$from, $to])
            ->get(['type', 'product_id', 'completed_by', 'created_at', 'completed_at']);

        /* ---------- Cakupan per toko (snapshot) ---------- */
        $stores = Store::with('brands')->where('is_active', true)->get()
            ->map(function ($store) use ($activeByBrand, $postings, $pendingTasks) {
                $targetSet = $store->brands->pluck('id')
                    ->flatMap(fn ($bid) => $activeByBrand[$bid] ?? collect())
                    ->flip();

                $posted = $postings->where('store_id', $store->id)
                    ->filter(fn ($p) => $targetSet->has($p->product_id))->count();

                $pending = $pendingTasks->where('store_id', $store->id)->countBy('type');

                return [
                    'store'          => $store,
                    'target'         => $targetSet->count(),
                    'posted'         => $posted,
                    'unposted'       => max($targetSet->count() - $posted, 0),
                    'pending_post'   => (int) ($pending[MarketplaceTask::TYPE_POSTING] ?? 0),
                    'pending_price'  => (int) ($pending[MarketplaceTask::TYPE_PRICE_UPDATE] ?? 0),
                    'pending_revisi' => (int) ($pending[MarketplaceTask::TYPE_REVISION] ?? 0),
                ];
            });

        /* ---------- Agregat per marketplace & brand ---------- */
        $byMarketplace = $stores->groupBy(fn ($r) => $r['store']->marketplace)->map(fn ($rows) => [
            'target'   => $rows->sum('target'),
            'posted'   => $rows->sum('posted'),
            'unposted' => $rows->sum('unposted'),
        ]);

        $brands = \App\Models\Brand::with('stores')->get()
            ->map(function ($brand) use ($activeByBrand, $postings) {
                $pSet   = ($activeByBrand[$brand->id] ?? collect())->flip();
                $sSet   = $brand->stores->where('is_active', true)->pluck('id')->flip();
                $target = $pSet->count() * $sSet->count();
                $posted = $postings->filter(fn ($p) => $pSet->has($p->product_id) && $sSet->has($p->store_id))->count();
                return [
                    'brand'    => $brand,
                    'target'   => $target,
                    'posted'   => $posted,
                    'unposted' => max($target - $posted, 0),
                ];
            })->filter(fn ($r) => $r['target'] > 0);

        /* ---------- Kinerja per PIC brand (terikat periode) ---------- */
        $users = \App\Models\User::with(['brands.stores'])->whereHas('brands')->get();

        // Hari masuk semua PIC: 1 query agregat, bukan 1 per orang
        $daysPresentByUser = \App\Models\Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('user_id, count(*) c')->groupBy('user_id')->pluck('c', 'user_id');

        $people = $users->map(function ($user) use ($from, $to, $activeByBrand, $allByBrand, $postings, $pendingTasks, $doneTasks, $daysPresentByUser) {
            $pSet = $user->brands->pluck('id')
                ->flatMap(fn ($bid) => $allByBrand[$bid] ?? collect())
                ->flip();

            $target = 0;
            foreach ($user->brands as $brand) {
                $target += ($activeByBrand[$brand->id] ?? collect())->count()
                         * $brand->stores->where('is_active', true)->count();
            }

            $posted   = $postings->filter(fn ($p) => $p->posted_by === $user->id && $pSet->has($p->product_id))->count();
            $unposted = max($target - $posted, 0);

            $tasks  = $doneTasks->filter(fn ($t) => $t->completed_by === $user->id && $pSet->has($t->product_id));
            $byType = $tasks->countBy('type');

            $priceUpdateTasks = $tasks->where('type', MarketplaceTask::TYPE_PRICE_UPDATE);
            $avgHours = null;
            if ($priceUpdateTasks->count() > 0) {
                $totalMinutes = $priceUpdateTasks->sum(fn ($t) => $t->created_at->diffInMinutes($t->completed_at));
                $avgHours = round($totalMinutes / $priceUpdateTasks->count() / 60, 1);
            }

            $pendingPrice      = $pendingTasks->filter(fn ($t) => $t->type === MarketplaceTask::TYPE_PRICE_UPDATE && $pSet->has($t->product_id));
            $pendingPriceCount = $pendingPrice->count();
            $oldestPending     = $pendingPrice->sortBy('created_at')->first();
            $oldestHours       = $oldestPending
                ? round($oldestPending->created_at->diffInMinutes(now()) / 60, 1)
                : null;

            $daysPresent = (int) ($daysPresentByUser[$user->id] ?? 0);

            $avgPostPerDay  = $daysPresent > 0 ? round((int) ($byType[MarketplaceTask::TYPE_POSTING] ?? 0) / $daysPresent, 1) : null;
            $avgTasksPerDay = $daysPresent > 0 ? round($tasks->count() / $daysPresent, 1) : null;

            $pendingAll     = $pendingTasks->filter(fn ($t) => $pSet->has($t->product_id))->count();
            $completionRate = ($tasks->count() + $pendingAll) > 0
                ? (int) round($tasks->count() / ($tasks->count() + $pendingAll) * 100)
                : null;

            return [
                'user'            => $user,
                'brands'          => $user->brands->pluck('name')->join(', '),
                'target'          => $target,
                'posted'          => $posted,
                'unposted'        => $unposted,
                'posting_done'    => (int) ($byType[MarketplaceTask::TYPE_POSTING] ?? 0),
                'price_done'      => (int) ($byType[MarketplaceTask::TYPE_PRICE_UPDATE] ?? 0),
                'revisi_done'     => (int) ($byType[MarketplaceTask::TYPE_REVISION] ?? 0),
                'avg_hours_price' => $avgHours,
                'pending_price'   => $pendingPriceCount,
                'oldest_hours'    => $oldestHours,
                'days_present'    => $daysPresent,
                'avg_post_day'    => $avgPostPerDay,
                'avg_tasks_day'   => $avgTasksPerDay,
                'completion_pct'  => $completionRate,
            ];
        })->values();

        /* ---------- Data grafik garis: posting per tanggal per orang ---------- */
        $chartLabels = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $chartLabels[] = $d->toDateString();
        }

        $chartColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899'];

        $chartDatasets = $people->map(function ($p, $i) use ($allByBrand, $doneTasks, $chartLabels, $chartColors) {
            $pSet = $p['user']->brands->pluck('id')
                ->flatMap(fn ($bid) => $allByBrand[$bid] ?? collect())
                ->flip();

            $perDay = $doneTasks
                ->filter(fn ($t) => $t->completed_by === $p['user']->id
                    && $t->type === MarketplaceTask::TYPE_POSTING
                    && $pSet->has($t->product_id))
                ->groupBy(fn ($t) => $t->completed_at->toDateString())
                ->map->count();

            return [
                'label'           => $p['user']->name,
                'data'            => collect($chartLabels)->map(fn ($d) => (int) ($perDay[$d] ?? 0)),
                'borderColor'     => $chartColors[$i % count($chartColors)],
                'backgroundColor' => $chartColors[$i % count($chartColors)],
                'tension'         => 0.3,
                'pointRadius'     => 3,
            ];
        })->values();

        /* ---------- Kotak PERLU PERHATIAN ---------- */
        $alerts = collect();

        // Update harga menggantung lama (per orang)
        foreach ($people as $p) {
            if ($p['oldest_hours'] !== null && $p['oldest_hours'] >= 24) {
                $alerts->push([
                    'level' => $p['oldest_hours'] >= 72 ? 'red' : 'yellow',
                    'msg'   => "Update harga {$p['user']->name} ({$p['brands']}) tertua " .
                               round($p['oldest_hours']) . " jam belum dikerjakan.",
                ]);
            }
        }

        // Revisi menumpuk
        $totalRevisi = $stores->sum('pending_revisi');
        if ($totalRevisi > 0) {
            $alerts->push([
                'level' => $totalRevisi >= 5 ? 'red' : 'yellow',
                'msg'   => "{$totalRevisi} tugas revisi menunggu di antrian.",
            ]);
        }

        // Toko belum posting sama sekali
        $tokoNolPosting = $stores->filter(fn ($r) => $r['posted'] === 0 && $r['target'] > 0);
        if ($tokoNolPosting->count() > 0) {
            $names = $tokoNolPosting->map(fn ($r) => $r['store']->name)->join(', ');
            $alerts->push([
                'level' => 'red',
                'msg'   => "{$tokoNolPosting->count()} toko belum ada posting sama sekali: {$names}.",
            ]);
        }

        // Brand belum ada toko sama sekali — kasus paling awal, brand baru dibikin nganggur total.
        $brandsNoStore = \App\Models\Brand::whereDoesntHave('stores')->get();
        if ($brandsNoStore->count() > 0) {
            $alerts->push([
                'level' => 'red',
                'msg'   => "Brand belum dipetakan ke toko manapun: " . $brandsNoStore->pluck('name')->join(', ') . ".",
            ]);
        }

        // Brand SUDAH ada toko tapi belum ada PIC — beda kasus dari di atas.
        $brandsNoPic = \App\Models\Brand::with('stores')
            ->whereDoesntHave('storePics')
            ->whereHas('stores')
            ->get();
        if ($brandsNoPic->count() > 0) {
            $alerts->push([
                'level' => 'yellow',
                'msg'   => "Brand punya toko tapi belum ada PIC: " . $brandsNoPic->pluck('name')->join(', ') . ".",
            ]);
        }

        /* ---------- Diskon alert (pindah dari dashboard utama) ---------- */
        $discountAlerts = \App\Models\ProductDiscount::with('stores:id,name')
            ->where('ends_at', '<=', now()->addDays(30))
            ->orderBy('ends_at')->get();

        return view('marketplace.dashboard', compact(
            'stores', 'byMarketplace', 'brands', 'people',
            'from', 'to', 'alerts', 'discountAlerts',
            'chartLabels', 'chartDatasets'
        ));
    }

    /** Banjir tugas yang DISENGAJA: buat tugas posting untuk semua produk target toko yang belum terposting. */
    public function generateBacklog(Request $request, Store $store)
    {
        $targetIds = Product::whereNull('archived_at')
            ->whereIn('brand_id', $store->brands->pluck('id'))
            ->pluck('id');

        $postedIds = Posting::where('store_id', $store->id)->pluck('product_id');

        $created = 0;
        foreach ($targetIds->diff($postedIds) as $productId) {
            $task = MarketplaceTask::firstOrCreate(
                [
                    'type'       => MarketplaceTask::TYPE_POSTING,
                    'product_id' => $productId,
                    'store_id'   => $store->id,
                    'status'     => MarketplaceTask::STATUS_PENDING,
                ],
                ['created_at' => now(), 'note' => 'Backlog — dibuat massal oleh CEO']
            );
            if ($task->wasRecentlyCreated) $created++;
        }

        return back()->with('ok', "{$created} tugas posting dibuat untuk {$store->name}.");
    }
}
