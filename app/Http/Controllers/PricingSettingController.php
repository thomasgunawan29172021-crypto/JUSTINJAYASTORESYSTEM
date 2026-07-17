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
        // Kombinasi diambil dari service — SUMBER YANG SAMA dengan yang dipakai
        // mesin hitung. Jangan pernah bikin query kombinasi sendiri di sini:
        // begitu dua-duanya beda, Thomas ngisi baris yang gak pernah dibaca.
        $combos     = $this->calculator->activeCombos();
        $categories = Category::orderBy('name')->get();

        // Prefetch semua fee dalam 1 query, di-index pakai kunci gabungan.
        // Tanpa ini: (jumlah kombinasi × jumlah kategori) query — N+1 klasik.
        $fees = MarketplaceCategoryFee::get()
            ->keyBy(fn ($f) => $f->marketplace.'|'.$f->tier.'|'.$f->category_id);

        return view('pricing.settings', [
            'settings'   => PricingSetting::current(),
            'categories' => $categories,
            'combos'     => $combos,
            'fees'       => $fees,
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

    /** Simpan grid biaya admin + ongkir sekaligus. */
    public function updateFees(Request $request)
    {
        // Normalisasi SEBELUM validate. Kalau kebalik, Thomas dapet error validasi
        // buat angka yang sebenernya bener menurut cara nulis Indonesia.
        $normalized = [];
        foreach ((array) $request->input('fees', []) as $key => $values) {
            $normalized[$key] = [
                'admin_percent' => $this->parseDecimal($values['admin_percent'] ?? null),
                'shipping_cost' => $this->parseInteger($values['shipping_cost'] ?? null),
            ];
        }
        $request->merge(['fees' => $normalized]);

        $request->validate([
            'fees'                 => ['array'],
            'fees.*.admin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fees.*.shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

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

            $isEmpty = $values['admin_percent'] === null && $values['shipping_cost'] === null;

            // Kosong & belum pernah ada → jangan bikin baris sampah.
            // Kosong TAPI barisnya ada → tetap disimpan, biar Thomas bisa
            // ngosongin lagi angka yang salah.
            if ($isEmpty && ! $existing->has($key)) {
                continue;
            }

            [$marketplace, $tier, $categoryId] = explode('|', $key);

            MarketplaceCategoryFee::updateOrCreate(
                [
                    'marketplace' => $marketplace,
                    'tier'        => $tier,
                    'category_id' => (int) $categoryId,
                ],
                [
                    // null tetap null — "belum diisi", BUKAN 0.
                    'admin_percent' => $values['admin_percent'],
                    'shipping_cost' => $values['shipping_cost'],
                ]
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

    /**
     * Rupiah: "10.000" / "10,000" / "Rp 10.000" → "10000". Kosong → null.
     * Rupiah gak pernah pecahan, jadi semua non-digit aman dibuang.
     * "0" HARUS tetap "0" — itu gratis ongkir, bukan belum diisi.
     */
    protected function parseInteger(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }
}