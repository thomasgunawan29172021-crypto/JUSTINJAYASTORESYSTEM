<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MarketplaceTask;
use App\Models\Posting;
use App\Models\Product;
use App\Models\Store;
use App\Services\PostingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'name_asc');
        [$col, $dir] = match ($sort) {
            'name_desc'    => ['name', 'desc'],
            'date_newest'  => ['created_at', 'desc'],
            'date_oldest'  => ['created_at', 'asc'],
            default        => ['name', 'asc'],
        };

            $products = Product::with([
                'brand.stores' => fn ($q) => $q->where('is_active', true),
                'prices',
                'postings.poster',
                'postings.corrector',
            ])
            ->when(! $request->boolean('archived'),
                fn ($q) => $q->whereNull('archived_at'),
                fn ($q) => $q->whereNotNull('archived_at'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim($request->string('q')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('sku', 'like', $term));
            })
            ->when($request->filled('brand_id'),
                fn ($q) => $q->where('brand_id', (int) $request->input('brand_id')))
            ->when($request->filled('store_id'),
                fn ($q) => $q->whereHas('postings',
                    fn ($p) => $p->where('store_id', (int) $request->input('store_id'))))
            ->orderBy($col, $dir)
            ->paginate(15)
            ->withQueryString();

        $activeStoreIds = Store::where('is_active', true)->pluck('id');

        $postedCounts = Posting::whereIn('product_id', $products->pluck('id'))
            ->whereIn('store_id', $activeStoreIds)
            ->selectRaw('product_id, count(*) c')
            ->groupBy('product_id')
            ->pluck('c', 'product_id');

        // get() dulu baru pluck — subselect withCount ikut terbawa dengan aman
        $targetPerBrand = Brand::withCount([
            'stores' => fn ($q) => $q->where('is_active', true),
        ])->get()->pluck('stores_count', 'id');

        return view('marketplace.products.index', [
            'products'       => $products,
            'brands'         => Brand::orderBy('name')->get(),
            'marketplaces'   => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
            'postedCounts'   => $postedCounts,
            'targetPerBrand' => $targetPerBrand,
            'stores'         => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('marketplace.products.create', $this->formData());
    }

    public function store(Request $request, PostingTaskService $tasks)
    {
        $data = $this->validated($request);

        [$created, $marked] = DB::transaction(function () use ($data, $request, $tasks) {
            $product = Product::create($data);
            $this->syncPrices($product, $request);
            $this->syncBundleItems($product, $request);

            return $tasks->generateForNewProduct(
                $product,
                array_map('intval', (array) $request->input('posted_stores', [])),
                $request->user()
            );
        });

        return redirect()->route('marketplace.products.index')
            ->with('ok', "Produk {$data['name']} ditambahkan — {$created} tugas posting dibuat, {$marked} toko ditandai sudah posting.");
    }

    public function edit(Product $product, PostingTaskService $tasks)
    {
        $product->load(['prices', 'postings.poster', 'postings.corrector', 'bundleItems.component']);

        return view('marketplace.products.edit', $this->formData() + [
            'product'      => $product,
            'targetStores' => $tasks->targetStores($product),
            'postingMap'   => $product->postings->keyBy('store_id'),
        ]);
    }
    public function update(Request $request, Product $product, PostingTaskService $tasks)
    {
        $data = $this->validated($request, $product);

        $taskCount = DB::transaction(function () use ($product, $data, $request, $tasks) {
            $product->update($data);
            $this->syncBundleItems($product, $request);
            $changed = $this->syncPrices($product, $request);

            return $product->isArchived() ? 0 : $tasks->generateForPriceChange($product, $changed);
        });

        $msg = "Produk {$product->name} diperbarui.";
        if ($taskCount) {
            $msg .= " {$taskCount} tugas update harga dibuat.";
        }

        return redirect()->route('marketplace.products.index')->with('ok', $msg);
    }

    /**
     * Koreksi status posting per toko — CEO only (input mundur / perbaikan data).
     * posted_by = null → TIDAK dikreditkan ke PIC manapun (metrik produktivitas tetap jujur).
     */
    public function updatePostings(Request $request, Product $product, PostingTaskService $tasks)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa mengubah status posting.');

        $data = $request->validate([
            'posted_stores'   => ['nullable', 'array'],
            'posted_stores.*' => ['exists:stores,id'],
        ]);

        $targetIds = $tasks->targetStores($product)->pluck('id')->all();
        // Hanya toko target brand ini yang boleh disentuh — centangan lain diabaikan.
        $checked = array_intersect(array_map('intval', $data['posted_stores'] ?? []), $targetIds);

        $added = 0; $removed = 0;

        DB::transaction(function () use ($request, $product, $checked, $targetIds, &$added, &$removed) {
            $existing = \App\Models\Posting::where('product_id', $product->id)
                ->whereIn('store_id', $targetIds)->pluck('id', 'store_id');

            foreach ($targetIds as $storeId) {
                $has    = $existing->has($storeId);
                $should = in_array($storeId, $checked, true);

                if ($should && ! $has) {
                    \App\Models\Posting::create([
                        'product_id'   => $product->id,
                        'store_id'     => $storeId,
                        'posted_by'    => null,                 // tetap null — nol kredit PIC
                        'corrected_by' => $request->user()->id, // jejak: siapa yang koreksi
                        'posted_at'    => now(),
                    ]);
                    // Tugas posting pending jadi tak relevan → buang dari antrian PIC
                    MarketplaceTask::where('product_id', $product->id)
                        ->where('store_id', $storeId)
                        ->where('type', MarketplaceTask::TYPE_POSTING)
                        ->where('status', MarketplaceTask::STATUS_PENDING)
                        ->delete();
                    $added++;
                } elseif (! $should && $has) {
                    \App\Models\Posting::where('product_id', $product->id)
                        ->where('store_id', $storeId)->delete();
                    $removed++;

                    // Munculkan lagi tugas posting buat PIC toko ini (kalau belum ada).
                    MarketplaceTask::firstOrCreate(
                        [
                            'type'       => MarketplaceTask::TYPE_POSTING,
                            'product_id' => $product->id,
                            'store_id'   => $storeId,
                            'status'     => MarketplaceTask::STATUS_PENDING,
                        ],
                        ['created_at' => now(), 'note' => 'Dibuat ulang — status posting dikoreksi CEO']
                    );
                }
            }
        });

        return back()->with('ok', "Status posting {$product->name}: {$added} ditandai posting, {$removed} dicabut.");
    }

    /** Arsip / aktifkan lagi + set produk pengganti. */
    public function archive(Request $request, Product $product)
    {
        if ($product->isArchived()) {
            $product->update(['archived_at' => null, 'replacement_product_id' => null]);

            return back()->with('ok', "Produk {$product->name} diaktifkan lagi.");
        }

        $data = $request->validate([
            'replacement_product_id' => ['nullable', 'exists:products,id', Rule::notIn([$product->id])],
        ]);

        $product->update([
            'archived_at'            => now(),
            'replacement_product_id' => $data['replacement_product_id'] ?? null,
        ]);

        MarketplaceTask::where('product_id', $product->id)
            ->where('status', MarketplaceTask::STATUS_PENDING)->delete();

        return back()->with('ok', "Produk {$product->name} diarsipkan.");
    }

    /* ------------------------- Import CSV ------------------------- */

    public function importForm()
    {
        return view('marketplace.products.import', [
            'marketplaces'   => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
            'postingColumns' => $this->postingColumns(),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle, null, ',', '"', '') ?: []);

        if (! in_array('nama', $header) || ! in_array('brand', $header)) {
            return back()->withErrors(['file' => 'Kolom wajib tidak ditemukan: minimal harus ada "nama" dan "brand" di baris pertama.']);
        }

        $marketplaces   = Store::where('is_active', true)->pluck('marketplace')->unique();
        $postingColumns = $this->postingColumns();

        // Matriks posting MENETAPKAN status posting langsung — tanpa tugas, tanpa kredit PIC.
        // Route ini sudah CEO-only (middleware 'ceo'); guard dipertahankan agar tetap aman
        // seandainya route dipindah keluar dari grup CEO.
        $canSetPosting = $request->user()->role->isCeo();

        $created = 0; $updated = 0; $skipped = 0; $newBrands = [];
        $posted = 0; $unposted = 0;

        DB::transaction(function () use ($handle, $header, $marketplaces, $postingColumns, $canSetPosting, &$created, &$updated, &$skipped, &$newBrands, &$posted, &$unposted) {
            while (($raw = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $row = array_combine($header, array_pad($raw, count($header), null));

                $name      = trim($row['nama'] ?? '');
                $brandName = trim($row['brand'] ?? '');

                if ($name === '' || $brandName === '') {
                    $skipped++;
                    continue;
                }

                $brand = Brand::firstOrCreate(['name' => $brandName]);
                if ($brand->wasRecentlyCreated) {
                    $newBrands[] = $brandName;
                }

                $num = fn ($key) => isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null
                    ? (int) preg_replace('/\D/', '', $row[$key])
                    : null;

                $product = Product::withTrashed()->firstOrNew(['name' => $name]);
                $isNew   = ! $product->exists;

                if (! $isNew && $product->trashed()) {
                    $product->restore(); // nama match produk di sampah → pulihkan, jangan crash
                }

                $productData = [
                    'brand_id'      => $brand->id,
                    'cost_price'    => $num('harga_beli') ?? 0,
                    'price_offline' => $num('harga_offline') ?? 0,
                    'price_grosir'  => $num('harga_grosir') ?? 0,
                ];

                // SKU dan barcode diperlakukan sebagai string agar angka nol di depan tidak dibuang.
                // Header lama tanpa kolom ini tetap aman; sel kosong juga tidak menimpa data yang sudah ada.
                foreach (['sku', 'barcode'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $value = trim((string) ($row[$field] ?? ''));

                        if ($value !== '') {
                            $productData[$field] = $value;
                        }
                    }
                }

                $product->fill($productData)->save();

                $isNew ? $created++ : $updated++;

                // Kolom harga per marketplace: {marketplace}_mall dan {marketplace}_biasa
                foreach ($marketplaces as $mp) {
                    $mall    = $num("{$mp}_mall");
                    $regular = $num("{$mp}_biasa");

                    if ($mall !== null || $regular !== null) {
                        $product->prices()->updateOrCreate(
                            ['marketplace' => $mp],
                            ['price_mall' => $mall, 'price_regular' => $regular]
                        );
                    }
                }

                // Matriks posting: kolom "post_{nama toko}" berisi v (sudah) / x (belum).
                // Sel KOSONG = status toko itu tidak disentuh → CSV tanpa kolom ini tetap aman.
                if (! $canSetPosting) {
                    continue;
                }

                foreach ($postingColumns as $key => $store) {
                    $cell = strtolower(trim((string) ($row[$key] ?? '')));

                    if ($cell === '') {
                        continue;
                    }

                    if (in_array($cell, ['v', 'ya', 'yes', 'y', '1', 'true', 'centang'], true)) {
                        // posted_by = null → input mundur, TIDAK dikreditkan ke PIC mana pun.
                        $posting = Posting::firstOrCreate(
                            ['product_id' => $product->id, 'store_id' => $store->id],
                            ['posted_by' => null, 'posted_at' => now()]
                        );

                        // Sudah posted sebelumnya → jangan sentuh apa pun (re-import file yang sama = nol perubahan).
                        if (! $posting->wasRecentlyCreated) {
                            continue;
                        }

                        // Baru ditandai posted → tugas posting yang masih pending jadi basi.
                        MarketplaceTask::where('type', MarketplaceTask::TYPE_POSTING)
                            ->where('product_id', $product->id)
                            ->where('store_id', $store->id)
                            ->where('status', MarketplaceTask::STATUS_PENDING)
                            ->delete();

                        $posted++;

                        continue;
                    }

                    // "x" = belum posting → hapus posting bila ada (koreksi CEO).
                    // Tidak membuat tugas baru: backlog digenerate terpisah dari Dashboard MP.
                    $removed = Posting::where('product_id', $product->id)
                        ->where('store_id', $store->id)
                        ->delete();

                    if ($removed) {
                        // Tugas update harga hanya berlaku untuk toko yang sudah posting → jadi basi.
                        MarketplaceTask::where('type', MarketplaceTask::TYPE_PRICE_UPDATE)
                            ->where('product_id', $product->id)
                            ->where('store_id', $store->id)
                            ->where('status', MarketplaceTask::STATUS_PENDING)
                            ->delete();

                        $unposted++;
                    }
                }
            }
        });

        fclose($handle);

        $msg = "Import selesai: {$created} produk baru, {$updated} diperbarui, {$skipped} baris dilewati.";
        if ($posted || $unposted) {
            $msg .= " Matriks posting: {$posted} ditandai sudah posting, {$unposted} dibatalkan.";
        }
        if ($newBrands) {
            $msg .= ' ⚠️ Brand BARU tercipta: '.implode(', ', array_unique($newBrands)).' — cek typo & petakan ke toko di menu Brand!';
        }

        return redirect()->route('marketplace.products.index')->with('ok', $msg);
    }

    /**
     * Kolom matriks posting: 1 kolom per toko aktif, key "post_{nama toko}".
     * Prefix "post_" supaya tidak bentrok dengan kolom harga ({marketplace}_mall).
     *
     * Nama toko yang kembar antar-marketplace (mis. "JJ Official" di Shopee DAN TikTok)
     * dibedakan jadi "post_{nama} ({marketplace})". Tanpa ini header-nya dobel dan saat
     * import satu toko diam-diam tidak ter-update.
     *
     * Dipakai bersama oleh export & import — key-nya dijamin identik.
     *
     * @return \Illuminate\Support\Collection<string, Store> key kolom (lowercase) → Store
     */
    protected function postingColumns()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        $duplicateNames = $stores
            ->countBy(fn (Store $s) => strtolower(trim($s->name)))
            ->filter(fn (int $count) => $count > 1);

        return $stores->keyBy(function (Store $s) use ($duplicateNames) {
            $name = strtolower(trim($s->name));

            return $duplicateNames->has($name)
                ? "post_{$name} ({$s->marketplace})"
                : "post_{$name}";
        });
    }

    /** Export CSV — SKU, barcode, harga + matriks posting per toko (v/x). Round-trip aman. */
    public function export()
    {
        $marketplaces   = Store::where('is_active', true)->pluck('marketplace')->unique()->values();
        $postingColumns = $this->postingColumns();

        $header = ['nama', 'brand', 'sku', 'barcode', 'harga_beli', 'harga_offline', 'harga_grosir'];
        foreach ($marketplaces as $mp) {
            $header[] = "{$mp}_mall";
            $header[] = "{$mp}_biasa";
        }
        foreach ($postingColumns->keys() as $key) {
            $header[] = $key;
        }

        $products = Product::with(['brand', 'prices', 'postings:id,product_id,store_id'])
            ->whereNull('archived_at')->orderBy('name')->get();

        return response()->streamDownload(function () use ($products, $marketplaces, $postingColumns, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');

            foreach ($products as $p) {
                $row = [
                    $p->name,
                    $p->brand->name,
                    $p->sku ?? '',
                    $p->barcode ?? '',
                    $p->cost_price,
                    $p->price_offline,
                    $p->price_grosir,
                ];

                foreach ($marketplaces as $mp) {
                    $price = $p->prices->firstWhere('marketplace', $mp);
                    $row[] = $price?->price_mall ?? '';     // kosong, bukan 0 — supaya re-import tetap null
                    $row[] = $price?->price_regular ?? '';
                }

                $postedStoreIds = $p->postings->pluck('store_id')->flip();
                foreach ($postingColumns as $store) {
                    $row[] = $postedStoreIds->has($store->id) ? 'v' : 'x';
                }

                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, 'produk-'.now()->format('Y-m-d-Hi').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Pemetaan tier ↔ slot kolom harga. SATU-SATUNYA tempat mapping ini hidup. */
    protected const SLOT_BY_TIER = ['mall' => 'mall', 'biasa' => 'regular'];

    /**
     * Harga rekomendasi + evaluasi untung/rugi — dipanggil JS live dari form Produk.
     *
     * Lewat endpoint, BUKAN dihitung di JavaScript: rumusnya cuma boleh hidup di SATU
     * tempat (PricingCalculatorService). Kalau dikembarin di JS, suatu hari dua-duanya
     * beda dan Thomas lihat angka yang bukan angka sistem — tanpa error apa pun.
     */
    public function priceRecommendation(Request $request, \App\Services\PricingCalculatorService $calculator)
    {
        abort_unless($request->user()->role->isCeo(), 403);

        $request->merge([
            'program_extra_percent' => $this->parsePercent($request->input('program_extra_percent')),
            'program_extra_amount'  => $this->parseMoney($request->input('program_extra_amount')),
        ]);

        $data = $request->validate([
            'brand_id'    => ['nullable', 'exists:brands,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cost_price'  => ['nullable', 'integer', 'min:0'],
            'program_extra_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'program_extra_amount'  => ['nullable', 'integer', 'min:0'],
            'is_bundle'       => ['nullable', 'boolean'],
            'components'      => ['nullable', 'array'],
            'components.*.id' => ['nullable', 'integer'],
            'components.*.qty'=> ['nullable', 'integer', 'min:1'],
            'prices'      => ['nullable', 'array'],
        ]);

        // Instance SEMENTARA — gak pernah kena ->save(). Ini yang bikin halaman
        // "Produk Baru" bisa nampilin rekomendasi sebelum produknya disimpan.
        $product = new Product([
            'category_id' => $data['category_id'] ?? null,
            'cost_price'  => (int) ($data['cost_price'] ?? 0),
            // null ≠ 0 — jangan ?? 0 di sini.
            'program_extra_percent' => $data['program_extra_percent'] ?? null,
            'program_extra_amount'  => $data['program_extra_amount'] ?? null,
        ]);

        $product->is_bundle = (bool) ($data['is_bundle'] ?? false);

        // Relasi di-set EKSPLISIT: model ini gak pernah disimpan, jadi lazy-load
        // bakal query pakai id null dan balik kosong.
        if ($product->is_bundle) {
            $rows = (array) ($data['components'] ?? []);

            // 1 query buat semua komponen — jangan find() di dalam loop.
            $found = Product::with('brand')
                ->whereIn('id', array_filter(array_column($rows, 'id')))
                ->get()->keyBy('id');

            $items = collect($rows)
                ->map(function (array $row) use ($found) {
                    $component = $found->get((int) ($row['id'] ?? 0));

                    if (! $component) {
                        return null;
                    }

                    $item = new \App\Models\BundleItem(['qty' => max(1, (int) ($row['qty'] ?? 1))]);
                    $item->setRelation('component', $component);

                    return $item;
                })
                ->filter()->values();

            $product->setRelation('bundleItems', $items);
        } else {
            $product->setRelation('brand', $data['brand_id']
                ? Brand::withTrashed()->find($data['brand_id'])
                : null);
            $product->setRelation('bundleItems', collect());
        }

        $typed = [];
        foreach ((array) ($data['prices'] ?? []) as $key => $value) {
            // JS ngirim key "marketplace|slot" (ikut nama kolom form). Diterjemahin ke
            // "marketplace|tier" di sini — JS gak perlu tau soal tier sama sekali.
            [$marketplace, $slot] = array_pad(explode('|', (string) $key, 2), 2, null);
            $tier = array_search($slot, self::SLOT_BY_TIER, true);

            if ($marketplace === null || $tier === false) {
                continue;
            }

            $digits = preg_replace('/\D/', '', (string) $value);

            if ($digits !== '' && (int) $digits > 0) {
                $typed[$marketplace.'|'.$tier] = (int) $digits;
            }
        }

        $result = $calculator->calculate($product, $typed);

        $rows = array_map(fn (array $row) => [
            'marketplace' => $row['marketplace'],
            'tier'        => $row['tier'],
            'price'       => $row['price'],
            'error'       => $row['error'],
            'evaluation'  => $row['evaluation'],
            // Tier selain mall/biasa BELUM punya slot: price_mall & price_regular cuma
            // dua kolom. null → tombol "pakai" mati, angkanya tetap ditampilkan.
            // Utang yang ditunda, bukan dilupain.
            'slot'        => self::SLOT_BY_TIER[$row['tier']] ?? null,
        ], $result['rows']);

        // breakdown SENGAJA dibuang — keputusan Thomas: form Produk cuma angka jadi.
        // Modal ditampilkan read-only di form bundle. Dihitung di SINI, bukan di JS:
        // JS gak boleh tau soal program bertingkat — itu cuma hidup di model.
        return response()->json([
            'blockers' => $result['blockers'],
            'rows'     => $rows,
            'modal'    => [
                'raw'   => (int) round($product->rawCost()),
                'after' => (int) round($product->costAfterProgram()),
            ],
        ]);
    }

    /* ------------------------- Sampah ------------------------- */

    public function destroy(Product $product)
    {
        MarketplaceTask::where('product_id', $product->id)
            ->where('status', MarketplaceTask::STATUS_PENDING)->delete();

        $product->delete(); // soft — masuk sampah, auto-purge permanen setelah 7 hari

        return back()->with('ok', "Produk {$product->name} dipindah ke sampah.");
    }

    public function trash()
    {
        return view('marketplace.products.index', [
            // brand di-withTrashed supaya produk di sampah yang brand-nya juga di sampah gak bikin $p->brand->name crash
            'products'     => Product::onlyTrashed()
                ->with(['brand' => fn ($q) => $q->withTrashed(), 'prices'])
                ->orderBy('deleted_at')->get(),
            'brands'       => Brand::orderBy('name')->get(),
            'marketplaces' => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
            'trashView'    => true,
        ]);
    }

    public function restore(int $id)
    {
        Product::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('ok', 'Produk dipulihkan.');
    }

    public function clearTrash()
    {
        $skipped = 0;
        foreach (Product::onlyTrashed()->get() as $product) {
            try {
                $product->forceDelete();
            } catch (\Illuminate\Database\QueryException) {
                $skipped++; // masih direferensikan data lain — jangan paksa
            }
        }

        $msg = 'Sampah produk dikosongkan.';
        if ($skipped) $msg .= " {$skipped} produk dilewati (masih dipakai data lain).";

        return back()->with('ok', $msg);
    }

    /* ------------------------- Helper ------------------------- */

    protected function formData(): array
    {
        return [
            'brands'       => Brand::orderBy('name')->get(),
            'categories'   => \App\Models\Category::orderBy('name')->get(),
            'marketplaces' => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
            'allProducts'  => Product::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'stores'       => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
            // Kandidat komponen: produk biasa doang. Bundle gak boleh isi bundle —
            // nested = rekursi, dan gak ada gunanya buat toko HP.
            'components'   => Product::where('is_bundle', false)->whereNull('archived_at')
                ->orderBy('name')->get(['id', 'name', 'cost_price']),
        ];
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        // Normalisasi SEBELUM validate — form Indonesia ngirim "10,5" / "5.000".
        $request->merge([
            'program_extra_percent' => $this->parsePercent($request->input('program_extra_percent')),
            'program_extra_amount'  => $this->parseMoney($request->input('program_extra_amount')),
        ]);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:200',
                Rule::unique('products', 'name')->ignore($product?->id)],
            'barcode'       => ['nullable', 'string', 'max:100'],
            'sku'           => ['nullable', 'string', 'max:100'],
            'brand_id'      => ['required', 'exists:brands,id'],
            'category_id'   => ['nullable', 'exists:categories,id'],
            'program_extra_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'program_extra_amount'  => ['nullable', 'integer', 'min:0'],
            'cost_price'    => ['nullable', 'integer', 'min:0'],
            'price_offline' => ['nullable', 'integer', 'min:0'],
            'price_grosir'  => ['nullable', 'integer', 'min:0'],
            'is_bundle'          => ['nullable', 'boolean'],
            'components'         => ['nullable', 'array'],
            // Rule::exists dikunci is_bundle=false — bundle di dalam bundle ditolak
            // di level validasi, bukan cuma disembunyiin dari dropdown.
            'components.*.id'    => ['nullable', 'integer',
                Rule::exists('products', 'id')->where('is_bundle', false)],
            'components.*.qty'   => ['nullable', 'integer', 'min:1'],
        ]);

        // Kolom harga NOT NULL di DB: kosong berarti 0.
        // (Operator array + tidak menimpa key null — makanya di-set eksplisit.)
        foreach (['cost_price', 'price_offline', 'price_grosir'] as $field) {
            $data[$field] = (int) ($data[$field] ?? 0);
        }

        foreach (['barcode', 'sku'] as $field) {
            $value        = trim($data[$field] ?? '');
            $data[$field] = $value === '' ? null : $value;
        }

        // Key SELALU di-set supaya edit yang mengosongkan field beneran ngehapus nilainya.
        // Ini TAMBAHAN di atas program brand, bukan override — kosong = gak ada tambahan.
        $data['category_id']           = $data['category_id'] ?? null;
        $data['program_extra_percent'] = $data['program_extra_percent'] ?? null;
        $data['program_extra_amount']  = $data['program_extra_amount'] ?? null;

        // is_bundle cuma bisa diset waktu BIKIN. Produk lama gak bisa disulap jadi
        // bundle (dan sebaliknya) — itu ninggalin bundle_items yatim atau produk
        // yang harganya tiba-tiba jadi turunan. Kalau salah pilih, hapus & bikin ulang.
        if ($product === null) {
            $data['is_bundle'] = (bool) ($data['is_bundle'] ?? false);
        } else {
            unset($data['is_bundle']);
        }

        // Modal bundle itu TURUNAN dari komponen — kolomnya dipaksa 0 biar gak ada
        // dua sumber kebenaran. rawCost() emang gak baca kolom ini buat bundle.
        if (($data['is_bundle'] ?? $product?->is_bundle) === true) {
            $data['cost_price'] = 0;
        }

        unset($data['components']); // disimpan lewat syncBundleItems(), bukan mass-assign

        return $data;
    }

    /** "10,5" → "10.5". Kosong → null. */
    protected function parsePercent(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : str_replace(',', '.', $value);
    }

    /** "5.000" / "Rp 5.000" → "5000". Kosong → null. */
    protected function parseMoney(mixed $value): ?string
    {
        $value  = trim((string) ($value ?? ''));
        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    protected function syncPrices(Product $product, Request $request): array
    {
        $changed = [];

        foreach ((array) $request->input('mp', []) as $marketplace => $pair) {
            $mp      = strtolower($marketplace);
            $mall    = $pair['mall'] !== null && $pair['mall'] !== '' ? (int) preg_replace('/\D/', '', $pair['mall']) : null;
            $regular = $pair['regular'] !== null && $pair['regular'] !== '' ? (int) preg_replace('/\D/', '', $pair['regular']) : null;

            $row        = $product->prices()->where('marketplace', $mp)->first();
            $oldMall    = $row?->price_mall !== null ? (int) $row->price_mall : null;
            $oldRegular = $row?->price_regular !== null ? (int) $row->price_regular : null;

            // Baris baru ($row null) BUKAN "perubahan" — tugas posting sudah membawa harga terkini
            if ($row && ($oldMall !== $mall || $oldRegular !== $regular)) {
                $changed[] = $mp;
            }

            $product->prices()->updateOrCreate(
                ['marketplace' => $mp],
                ['price_mall' => $mall, 'price_regular' => $regular]
            );
        }

        return $changed;
    }

    /**
     * Rebuild komponen bundle. Hapus-lalu-bikin, bukan diff: jumlah komponen dikit
     * dan ini bikin urutan/qty selalu persis sama kayak yang di layar.
     */
    protected function syncBundleItems(Product $product, Request $request): void
    {
        if (! $product->is_bundle) {
            return;
        }

        $rows = [];

        foreach ((array) $request->input('components', []) as $row) {
            $id  = (int) ($row['id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);

            // Bundle gak boleh jadi komponen dirinya sendiri.
            if ($id <= 0 || $qty <= 0 || $id === $product->id) {
                continue;
            }

            // Produk sama dipilih dua kali → qty digabung, bukan error.
            // (unique(bundle_id, component_id) bakal nolak duplikat di level DB.)
            $rows[$id] = ($rows[$id] ?? 0) + $qty;
        }

        $product->bundleItems()->delete();

        foreach ($rows as $componentId => $qty) {
            $product->bundleItems()->create(['component_id' => $componentId, 'qty' => $qty]);
        }
    }
}