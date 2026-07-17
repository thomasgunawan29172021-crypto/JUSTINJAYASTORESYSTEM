<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MarketplaceCategoryFee;
use App\Models\PricingSetting;
use App\Services\PricingCalculatorService;
use Illuminate\Http\Request;

class PricingSettingController extends Controller
{
    public function __construct(protected PricingCalculatorService $calculator) {}

    public function index()
    {
        // Kombinasi diambil dari service — SUMBER YANG SAMA dengan mesin hitung.
        // Jangan bikin query kombinasi sendiri: begitu dua-duanya beda, Thomas ngisi
        // baris yang gak pernah dibaca.
        $combos     = $this->calculator->activeCombos();
        $categories = Category::orderBy('name')->get();

        // Prefetch semua fee dalam 1 query — tanpa ini (kombinasi × kategori) query.
        $fees = MarketplaceCategoryFee::get()
            ->keyBy(fn ($f) => $f->marketplace.'|'.$f->tier.'|'.$f->category_id);

        return view('pricing.settings', [
            'settings'   => PricingSetting::current(),
            'categories' => $categories,
            'combos'     => $combos,
            'fees'       => $fees,
            'feeFields'  => MarketplaceCategoryFee::PERCENT_FIELDS,
        ]);
    }

    /** Pajak & margin global. */
    public function update(Request $request)
    {
        // Normalisasi SEBELUM validate — 'numeric' nolak "3,5" mentah-mentah.
        $request->merge([
            'tax_percent'    => $this->parseDecimal($request->input('tax_percent')),
            'margin_percent' => $this->parseDecimal($request->input('margin_percent')),
        ]);

        $data = $request->validate([
            // nullable = boleh dikosongin lagi. Calculator bakal nolak hitung
            // dan bilang "belum diset" — itu perilaku yang bener, bukan bug.
            'tax_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);

        PricingSetting::current()->update($data);

        return back()->with('ok', 'Pengaturan pajak & margin disimpan.');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);

        Category::create($data);

        return back()->with('ok', "Kategori {$data['name']} ditambahkan.");
    }

    public function destroyCategory(Category $category)
    {
        // withTrashed() WAJIB: produk di Sampah tetap nyantol ke kategori ini di
        // level DB (restrictOnDelete). Tanpa withTrashed, cek ini lolos tapi
        // MySQL yang nolak — errornya mentah dan gak kebaca Thomas.
        $used = $category->products()->withTrashed()->count();

        if ($used > 0) {
            return back()->with('err', "Kategori {$category->name} masih dipakai {$used} produk — pindahkan produknya dulu.");
        }

        $category->delete(); // baris biaya ikut kehapus (cascade)

        return back()->with('ok', "Kategori {$category->name} dihapus.");
    }

    /** Simpan grid biaya — semua kolom persen (ongkir nominal udah dibuang). */
    public function updateFees(Request $request)
    {
        $fields = array_keys(MarketplaceCategoryFee::PERCENT_FIELDS);

        // Normalisasi SEBELUM validate. Kalau kebalik, Thomas dapet error validasi
        // buat angka yang bener menurut cara nulis Indonesia.
        $normalized = [];
        foreach ((array) $request->input('fees', []) as $key => $values) {
            $row = [];
            foreach ($fields as $f) {
                $row[$f] = $this->parseDecimal($values[$f] ?? null);
            }
            $normalized[$key] = $row;
        }
        $request->merge(['fees' => $normalized]);

        $rules = ['fees' => ['array']];
        foreach ($fields as $f) {
            $rules["fees.*.{$f}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }
        $request->validate($rules);

        // Dua-duanya ditarik SEKALI di luar loop.
        $categoryIds = Category::pluck('id');
        $existing = MarketplaceCategoryFee::get()
            ->keyBy(fn ($f) => $f->marketplace.'|'.$f->tier.'|'.$f->category_id);

        // Whitelist: cuma kombinasi yang beneran ada di toko aktif × kategori yang
        // beneran ada. Kunci datang dari form — jangan dipercaya mentah-mentah.
        $valid = [];
        foreach ($this->calculator->activeCombos() as $combo) {
            foreach ($categoryIds as $catId) {
                $valid[$combo['marketplace'].'|'.$combo['tier'].'|'.$catId] = true;
            }
        }

        $saved = 0;

        foreach ($request->input('fees', []) as $key => $values) {
            if (! isset($valid[$key])) {
                continue;
            }

            $isEmpty = collect($fields)->every(fn ($f) => ($values[$f] ?? null) === null);

            // Kosong & belum pernah ada → jangan bikin baris sampah.
            // Kosong TAPI barisnya ada → tetap disimpan, biar Thomas bisa ngosongin lagi.
            if ($isEmpty && ! $existing->has($key)) {
                continue;
            }

            [$marketplace, $tier, $categoryId] = explode('|', $key);

            MarketplaceCategoryFee::updateOrCreate(
                ['marketplace' => $marketplace, 'tier' => $tier, 'category_id' => (int) $categoryId],
                // null tetap null — "belum diisi", BUKAN 0.
                array_intersect_key($values, array_flip($fields))
            );

            $saved++;
        }

        return back()->with('ok', "Biaya disimpan ({$saved} baris).");
    }

    /**
     * Persen: "3,5" dan "3.5" → "3.5". Kosong → null.
     *
     * Titik SENGAJA gak dibuang: di field persen, titik gak pernah jadi pemisah
     * ribuan (3.5% itu tiga koma lima, bukan tiga ribu lima ratus persen). Kalau
     * titik dibuang, "3.5" jadi "35" — sepuluh kali lipat, tanpa error apa pun.
     */
    protected function parseDecimal(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return str_replace(',', '.', $value);
    }
}