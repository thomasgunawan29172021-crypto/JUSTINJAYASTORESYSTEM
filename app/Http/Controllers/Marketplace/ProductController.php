<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MarketplaceTask;
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

        $products = Product::with(['brand', 'prices'])
            ->when(! $request->boolean('archived'),
                fn ($q) => $q->whereNull('archived_at'),
                fn ($q) => $q->whereNotNull('archived_at'))
            ->when($request->filled('q'),
                fn ($q) => $q->where('name', 'like', '%'.trim($request->string('q')).'%'))
            ->when($request->filled('brand_id'),
                fn ($q) => $q->where('brand_id', (int) $request->input('brand_id')))
            ->orderBy($col, $dir)
            ->paginate(15)
            ->withQueryString();

        $activeStoreIds = Store::where('is_active', true)->pluck('id');

        $postedCounts = \App\Models\Posting::whereIn('product_id', $products->pluck('id'))
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

            return $tasks->generateForNewProduct(
                $product,
                array_map('intval', (array) $request->input('posted_stores', [])),
                $request->user()
            );
        });

        return redirect()->route('marketplace.products.index')
            ->with('ok', "Produk {$data['name']} ditambahkan — {$created} tugas posting dibuat, {$marked} toko ditandai sudah posting.");
    }

    public function edit(Product $product)
    {
        return view('marketplace.products.edit', $this->formData() + [
            'product' => $product->load('prices'),
        ]);
    }

    public function update(Request $request, Product $product, PostingTaskService $tasks)
    {
        $data = $this->validated($request, $product);

        $taskCount = DB::transaction(function () use ($product, $data, $request, $tasks) {
            $product->update($data);
            $changed = $this->syncPrices($product, $request);

            return $product->isArchived() ? 0 : $tasks->generateForPriceChange($product, $changed);
        });

        $msg = "Produk {$product->name} diperbarui.";
        if ($taskCount) {
            $msg .= " {$taskCount} tugas update harga dibuat.";
        }

        return redirect()->route('marketplace.products.index')->with('ok', $msg);
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
            'marketplaces' => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
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

        $marketplaces = Store::where('is_active', true)->pluck('marketplace')->unique();
        $created = 0; $updated = 0; $skipped = 0; $newBrands = [];

        DB::transaction(function () use ($handle, $header, $marketplaces, &$created, &$updated, &$skipped, &$newBrands) {
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

                $product->fill([
                    'brand_id'      => $brand->id,
                    'cost_price'    => $num('harga_beli') ?? 0,
                    'price_offline' => $num('harga_offline') ?? 0,
                    'price_grosir'  => $num('harga_grosir') ?? 0,
                ])->save();

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
            }
        });

        fclose($handle);

        $msg = "Import selesai: {$created} produk baru, {$updated} diperbarui, {$skipped} baris dilewati.";
        if ($newBrands) {
            $msg .= ' ⚠️ Brand BARU tercipta: '.implode(', ', array_unique($newBrands)).' — cek typo & petakan ke toko di menu Brand!';
        }

        return redirect()->route('marketplace.products.index')->with('ok', $msg);
    }

    /** Export CSV — format kolom SAMA dengan format import (round-trip aman). */
    public function export()
    {
        $marketplaces = Store::where('is_active', true)->pluck('marketplace')->unique()->values();

        $header = ['nama', 'brand', 'harga_beli', 'harga_offline', 'harga_grosir'];
        foreach ($marketplaces as $mp) {
            $header[] = "{$mp}_mall";
            $header[] = "{$mp}_biasa";
        }

        $products = Product::with(['brand', 'prices'])->whereNull('archived_at')->orderBy('name')->get();

        return response()->streamDownload(function () use ($products, $marketplaces, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');

            foreach ($products as $p) {
                $row = [$p->name, $p->brand->name, $p->cost_price, $p->price_offline, $p->price_grosir];

                foreach ($marketplaces as $mp) {
                    $price = $p->prices->firstWhere('marketplace', $mp);
                    $row[] = $price?->price_mall ?? '';     // kosong, bukan 0 — supaya re-import tetap null
                    $row[] = $price?->price_regular ?? '';
                }

                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, 'produk-'.now()->format('Y-m-d-Hi').'.csv', ['Content-Type' => 'text/csv']);
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
            'marketplaces' => Store::where('is_active', true)->pluck('marketplace')->unique()->values(),
            'allProducts'  => Product::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'stores'       => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:200',
                Rule::unique('products', 'name')->ignore($product?->id)],
            'brand_id'      => ['required', 'exists:brands,id'],
            'cost_price'    => ['nullable', 'integer', 'min:0'],
            'price_offline' => ['nullable', 'integer', 'min:0'],
            'price_grosir'  => ['nullable', 'integer', 'min:0'],
        ]);

        // Kolom harga NOT NULL di DB: kosong berarti 0.
        // (Operator array + tidak menimpa key null — makanya di-set eksplisit.)
        foreach (['cost_price', 'price_offline', 'price_grosir'] as $field) {
            $data[$field] = (int) ($data[$field] ?? 0);
        }

        return $data;
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
}
