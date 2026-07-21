<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceTask;
use App\Models\Posting;
use App\Models\BrandStorePic;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $isCeo = $user->isCeo();

        // Filter periode: pending pakai created_at, selesai pakai completed_at
        $range = $request->string('range')->toString();
        $since = match ($range) {
            'today' => now()->startOfDay(),
            '7d'    => now()->subDays(7)->startOfDay(),
            '30d'   => now()->subDays(30)->startOfDay(),
            default => null,
        };

        // Nilai tak dikenal (mis. ?range=ngawur) → perlakukan sebagai "semua waktu".
        // Dinormalkan di sini supaya view tidak perlu menebak-nebak label periodenya.
        if ($since === null) {
            $range = '';
        }

        // SATU kata kunci untuk dua daftar: antrian pending DAN riwayat selesai.
        $q = trim($request->string('q')->toString());
        // Filter toko & brand (permintaan Thomas — biar gampang melacak).
        $storeId = (int) $request->input('store_id');
        $brandId = (int) $request->input('brand_id');

        $pending = MarketplaceTask::with(['product.brand', 'product.prices', 'store'])
            ->where('status', MarketplaceTask::STATUS_PENDING)
            ->when(! $isCeo, fn ($qq) => $qq->whereExists(function ($sub) use ($user) {
                // PIC per brand+TOKO (Tahap B) — bukan lagi seluruh brand.
                $sub->select(DB::raw(1))
                    ->from('brand_store_user')
                    ->join('products', 'products.brand_id', '=', 'brand_store_user.brand_id')
                    ->whereColumn('products.id', 'marketplace_tasks.product_id')
                    ->whereColumn('brand_store_user.store_id', 'marketplace_tasks.store_id')
                    ->where('brand_store_user.user_id', $user->id);
            }))
            ->when($q !== '', fn ($qq) => $qq->whereHas('product',
                fn ($p) => $p->where('name', 'like', "%{$q}%")))
            ->when($storeId, fn ($qq) => $qq->where('store_id', $storeId))
            ->when($brandId, fn ($qq) => $qq->whereHas('product',
                fn ($p) => $p->where('brand_id', $brandId)))
            ->when($since, fn ($qq) => $qq->where('created_at', '>=', $since))
            ->orderByRaw('pinned_at IS NULL')
            ->orderByDesc('pinned_at')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($t) => $t->store->label());

        // Nama parameter halaman sengaja 'done_page', bukan 'page' — supaya kalau nanti
        // antrian pending ikut di-paginate, keduanya tidak saling reset.
        $recentDone = MarketplaceTask::with(['product', 'store', 'completer'])
            ->where('status', MarketplaceTask::STATUS_DONE)
            ->when(! $isCeo, fn ($qq) => $qq->where('completed_by', $user->id))
            ->when($since, fn ($qq) => $qq->where('completed_at', '>=', $since))
            // withTrashed penting: riwayat memuat produk "di sampah" — tanpa ini, tugas
            // milik produk terhapus tak akan ketemu padahal barisnya tetap tampil.
            ->when($q !== '', fn ($qq) => $qq->whereHas('product',
                fn ($p) => $p->withTrashed()->where('name', 'like', "%{$q}%")))
            ->when($storeId, fn ($qq) => $qq->where('store_id', $storeId))
            ->when($brandId, fn ($qq) => $qq->whereHas('product',
                fn ($p) => $p->withTrashed()->where('brand_id', $brandId)))
            ->orderByDesc('completed_at')
            ->paginate(10, ['*'], 'done_page')
            ->withQueryString();

            // CEO lihat semua; PIC hanya toko & brand yang dia pegang — biar tak ada
            // opsi yang sudah pasti nihil hasilnya.
            if ($isCeo) {
                $stores = Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get();
                $brands = Brand::orderBy('name')->get();
            } else {
                $picRows = BrandStorePic::where('user_id', $user->id)->get(['brand_id', 'store_id']);
                $stores  = Store::whereIn('id', $picRows->pluck('store_id')->unique())
                    ->orderBy('marketplace')->orderBy('name')->get();
                $brands  = Brand::whereIn('id', $picRows->pluck('brand_id')->unique())->orderBy('name')->get();
            }

        return view('marketplace.tasks.index', [
            'pending'    => $pending,
            'recentDone' => $recentDone,
            'isCeo'      => $isCeo,
            'range'      => $range,
            'q'          => $q,
            'stores'     => $stores,
            'brands'     => $brands,
            'storeId'    => $storeId,
            'brandId'    => $brandId,
        ]);
    }

    public function complete(Request $request, MarketplaceTask $task)
    {
        $user = $request->user();

        // PIC per brand+TOKO (Tahap B): dicek terhadap toko tugas ini SPESIFIK,
        // bukan seluruh brand lagi.
        $isPic = BrandStorePic::where('brand_id', $task->product->brand_id)
            ->where('store_id', $task->store_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isPic || $user->isCeo(), 403, 'Anda bukan PIC brand ini di toko tersebut.');

        if ($task->status !== MarketplaceTask::STATUS_PENDING) {
            return back()->withErrors(['task' => 'Tugas ini sudah diselesaikan.']);
        }

        DB::transaction(function () use ($task, $user) {
            $task->update([
                'status'       => MarketplaceTask::STATUS_DONE,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            // Selesai posting = produk resmi tercatat terposting di toko ini.
            // Inilah yang membuat perubahan harga berikutnya men-generate tugas update.
            if ($task->type === MarketplaceTask::TYPE_POSTING) {
                Posting::firstOrCreate(
                    ['product_id' => $task->product_id, 'store_id' => $task->store_id],
                    ['posted_by' => $user->id, 'posted_at' => now()]
                );
            }
        });

        return back()->with('ok', "Tugas \"{$task->typeLabel()} — {$task->product->name}\" selesai.");
    }

    /**
     * Selesaikan banyak tugas sekaligus.
     *
     * BEST-EFFORT, bukan all-or-nothing: kalau 10 dipilih dan 2 bukan haknya, yang 8
     * tetap jalan. Kalau semua dibatalin gara-gara 1 gagal, orang bakal nyoba ulang
     * berkali-kali sambil nebak-nebak yang mana biangnya.
     *
     * Tetap dibungkus transaction: tiap tugas yang lolos bikin DUA tulisan (task +
     * Posting), dan dua-duanya harus jadi atau gak sama sekali.
     */
    public function bulkComplete(Request $request)
    {
        $data = $request->validate([
            // max:200 — pagar biar gak ada yang ngirim ribuan id sekaligus.
            'task_ids'   => ['required', 'array', 'max:200'],
            'task_ids.*' => ['integer'],
        ], [
            'task_ids.required' => 'Belum ada tugas yang dipilih.',
        ]);

        $user  = $request->user();
        $isCeo = $user->isCeo();

        $tasks = MarketplaceTask::with('product')
            ->whereIn('id', $data['task_ids'])
            ->where('status', MarketplaceTask::STATUS_PENDING)
            ->get();

        // Hak PIC ditarik SEKALI. Kalau dicek per tugas pakai ->exists() di dalam loop,
        // 50 tugas = 50 query — persis N+1 yang udah bikin masalah di dashboard.
        $picKeys = collect();
        if (! $isCeo) {
            $picKeys = BrandStorePic::where('user_id', $user->id)
                ->get(['brand_id', 'store_id'])
                ->map(fn ($r) => $r->brand_id.'|'.$r->store_id)
                ->flip();
        }

        $done = 0;

        DB::transaction(function () use ($tasks, $user, $isCeo, $picKeys, &$done) {
            foreach ($tasks as $task) {
                if (! $isCeo && ! $picKeys->has($task->product->brand_id.'|'.$task->store_id)) {
                    continue; // bukan PIC brand ini di toko itu — lewati diam-diam, dihitung di bawah
                }

                $task->update([
                    'status'       => MarketplaceTask::STATUS_DONE,
                    'completed_by' => $user->id,
                    'completed_at' => now(),
                ]);

                // Selesai posting = produk resmi tercatat terposting di toko ini.
                // Inilah yang bikin perubahan harga berikutnya men-generate tugas update.
                if ($task->type === MarketplaceTask::TYPE_POSTING) {
                    Posting::firstOrCreate(
                        ['product_id' => $task->product_id, 'store_id' => $task->store_id],
                        ['posted_by' => $user->id, 'posted_at' => now()]
                    );
                }

                $done++;
            }
        });

        // Selisih = gabungan dari: bukan hak dia, keburu diselesaikan orang lain,
        // atau id-nya udah gak ada. Gak dipisah-pisah — yang dia butuh cuma tau
        // "gak semuanya kena", terus liat sendiri sisanya di antrian.
        $skipped = count($data['task_ids']) - $done;

        $msg = "{$done} tugas ditandai selesai.";
        if ($skipped > 0) {
            $msg .= " {$skipped} dilewati (bukan tugas Anda, atau sudah selesai).";
        }

        return $done > 0
            ? back()->with('ok', $msg)
            : back()->withErrors(['task' => $msg]);
    }

    public function undo(Request $request, MarketplaceTask $task)
    {
        $user = $request->user();

        abort_unless($task->completed_by === $user->id || $user->isCeo(), 403,
            'Hanya yang menyelesaikan tugas ini (atau CEO) yang bisa membatalkan.');

        if ($task->status !== MarketplaceTask::STATUS_DONE) {
            return back()->withErrors(['task' => 'Tugas ini belum berstatus selesai.']);
        }

        DB::transaction(function () use ($task) {
            // Cabut catatan posting HANYA jika lahir dari penyelesaian task ini
            if ($task->type === MarketplaceTask::TYPE_POSTING) {
                Posting::where('product_id', $task->product_id)
                    ->where('store_id', $task->store_id)
                    ->where('posted_by', $task->completed_by)
                    ->where('posted_at', '>=', $task->completed_at)
                    ->delete();
            }

            $task->update([
                'status'       => MarketplaceTask::STATUS_PENDING,
                'completed_by' => null,
                'completed_at' => null,
            ]);
        });

        return back()->with('ok', 'Tugas dikembalikan ke antrian.');
    }

    public function togglePin(Request $request, MarketplaceTask $task)
    {
        $user  = $request->user();
        $isPic = BrandStorePic::where('brand_id', $task->product->brand_id)
            ->where('store_id', $task->store_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isPic || $user->isCeo(), 403);

        $task->update(['pinned_at' => $task->pinned_at ? null : now()]);

        return back();
    }

    public function requestRevision(Request $request, MarketplaceTask $task)
    {
        // Keputusan Thomas: hanya CEO yang menentukan revisi
        abort_unless($request->user()->isCeo(), 403, 'Hanya CEO yang bisa meminta revisi.');

        $data = $request->validate([
            'note' => ['required', 'string', 'max:300'],
        ], [
            'note.required' => 'Tulis apa yang harus direvisi — PIC butuh tahu salahnya di mana.',
        ]);

        if ($task->status !== MarketplaceTask::STATUS_DONE) {
            return back()->withErrors(['task' => 'Revisi hanya bisa diminta untuk tugas yang sudah selesai.']);
        }

        MarketplaceTask::firstOrCreate(
            [
                'type'       => MarketplaceTask::TYPE_REVISION,
                'product_id' => $task->product_id,
                'store_id'   => $task->store_id,
                'status'     => MarketplaceTask::STATUS_PENDING,
            ],
            ['created_at' => now(), 'note' => $data['note']]
        );

        return back()->with('ok', 'Tugas revisi dibuat untuk '.$task->store->name.'.');
    }
}
