<?php

namespace App\Services;

use App\Models\MarketplaceCategoryFee;
use App\Models\PricingSetting;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Mesin harga jual rekomendasi + evaluasi untung/rugi.
 *
 *      P = M / (1 − a − t − m − p1 − p2 − p3)
 *
 *   M  = modal setelah semua program (lihat Product::costAfterProgram())
 *   a  = % biaya admin           ┐
 *   p1 = % program gratis ongkir │ per (marketplace × tier × kategori)
 *   p2 = % program diskon        │
 *   p3 = % program ekstra diskon ┘
 *   t  = % pajak (PPh Final) — global
 *   m  = % target margin bersih — global. Margin = untung ÷ HARGA JUAL, bukan ÷ modal.
 *        Rumus ini bentuknya begini justru KARENA itu: margin dari harga jual bikin
 *        harga jual muncul di dua sisi persamaan, jadi harus pindah ke penyebut.
 *
 * Ongkir nominal udah TIDAK ADA — semua potongan marketplace sekarang persen, jadi
 * pembilangnya cuma M.
 *
 * FASE 3 — evaluasi harga yang diketik sendiri:
 *
 *      untung = P × (1 − a − t − p1 − p2 − p3) − M
 *
 * Target margin sengaja gak ikut: untung itu fakta, bukan target. Kalau P = harga
 * rekomendasi, untung ÷ P harus ≈ target margin. Kalau nggak, salah satu rumus bocor.
 *
 * PRINSIP UTAMA: gagal harus BERISIK. Tiap angka yang belum diisi bikin pesan yang
 * nyebut persis apa yang kurang — bukan diam-diam dianggap nol. Harga yang keliatan
 * valid tapi salah jauh lebih bahaya daripada harga yang gak keluar sama sekali.
 */
class PricingCalculatorService
{
    /** Harga rekomendasi dibulatkan KE ATAS per kelipatan ini (keputusan Thomas). */
    public const ROUND_TO = 1000;

    /**
     * @param  array<string,int>  $typedPrices  key "marketplace|tier" => harga yang diketik
     * @return array{blockers: array<int,string>, rows: array<int,array>}
     */
    public function calculate(Product $product, array $typedPrices = []): array
    {
        $settings = PricingSetting::current();
        $blockers = $this->findBlockers($product, $settings);

        if ($blockers !== []) {
            return ['blockers' => $blockers, 'rows' => []];
        }

        $costAfterProgram = $product->costAfterProgram();
        $taxPercent       = $settings->tax_percent;
        $marginPercent    = $settings->margin_percent;

        // Prefetch semua baris biaya kategori ini dalam 1 query. Tanpa ini, tiap
        // kombinasi = 1 query (N+1) — pola yang udah bikin masalah di
        // AttendanceRecapController dan MarketplaceDashboardController.
        $fees = MarketplaceCategoryFee::where('category_id', $product->category_id)
            ->get()
            ->keyBy(fn (MarketplaceCategoryFee $f) => $this->key($f->marketplace, $f->tier));

        $rows = [];

        foreach ($this->activeCombos() as $combo) {
            $key = $this->key($combo['marketplace'], $combo['tier']);

            $rows[] = $this->calculateCombo(
                $combo, $fees, $costAfterProgram, $taxPercent, $marginPercent, $product,
                $typedPrices[$key] ?? null
            );
        }

        return ['blockers' => [], 'rows' => $rows];
    }

    /**
     * Masalah yang bikin SEMUA kombinasi mustahil — dicek duluan supaya Thomas gak
     * dikasih 5 baris error yang isinya keluhan yang sama.
     */
    protected function findBlockers(Product $product, PricingSetting $settings): array
    {
        $blockers = [];

        if ($product->category_id === null) {
            $blockers[] = 'Kategori produk belum dipilih — biaya admin & program gak bisa ditentukan tanpa kategori.';
        }

        if ($settings->margin_percent === null) {
            $blockers[] = 'Target margin belum diset di Pengaturan Harga.';
        }

        if ($settings->tax_percent === null) {
            $blockers[] = 'Pajak belum diset di Pengaturan Harga.';
        }

        // rawCost(), BUKAN cost_price: bundle kolom itu selalu 0 (modalnya turunan
        // dari komponen), jadi baca mentah bikin blocker palsu di setiap bundle.
        if ($product->is_bundle && $product->bundleItems->isEmpty()) {
            $blockers[] = 'Bundle belum punya komponen — tambahkan produk isinya dulu.';
        } elseif ($product->rawCost() <= 0) {
            $blockers[] = $product->is_bundle
                ? 'Modal komponen bundle masih 0 — isi harga beli produk komponennya dulu.'
                : 'Modal produk masih 0 — isi harga beli dulu.';
        } elseif ($product->costAfterProgram() <= 0) {
            // Potongan program lebih besar dari modal → M nol/negatif → harga ngaco.
            // Hampir pasti salah ketik, jadi tolak dengan jelas.
            $blockers[] = 'Potongan program lebih besar dari modal — cek program brand & tambahan diskon.';
        }

        return $blockers;
    }

    /**
     * Kombinasi (marketplace × tier) yang beneran ada di toko aktif.
     *
     * Sengaja diturunkan dari `stores`: nilai marketplace & tier di tabel biaya disalin
     * dari sini, jadi lookup-nya gak mungkin meleset gara-gara typo atau beda huruf.
     * Toko di Sampah otomatis kebuang (SoftDeletes di model Store).
     *
     * public — halaman Pengaturan Harga bikin grid biaya dari method yang SAMA.
     * Kalau controller ngitung kombinasi sendiri, suatu hari dua-duanya beda dan
     * Thomas ngisi baris yang gak pernah dibaca mesinnya.
     */
    public function activeCombos(): Collection
    {
        return Store::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'marketplace', 'tier'])
            ->groupBy(fn (Store $s) => $this->key($s->marketplace, $s->tier))
            ->map(fn (Collection $stores) => [
                'marketplace' => $stores->first()->marketplace,
                'tier'        => $stores->first()->tier,
                'store_names' => $stores->pluck('name')->all(),
            ])
            ->values();
    }

    protected function calculateCombo(
        array $combo,
        Collection $fees,
        float $costAfterProgram,
        float $taxPercent,
        float $marginPercent,
        Product $product,
        ?int $typedPrice = null
    ): array {
        $row = [
            'marketplace' => $combo['marketplace'],
            'tier'        => $combo['tier'],
            'store_names' => $combo['store_names'],
            'price'       => null,
            'error'       => null,
            'breakdown'   => null,
            'evaluation'  => null,
        ];

        $label = ucfirst($combo['marketplace']).' '.($combo['tier'] ?? '?')
            .' × '.$product->category->name;

        if ($combo['tier'] === null) {
            $row['error'] = 'Tier belum diatur untuk toko: '.implode(', ', $combo['store_names']).'.';

            return $row;
        }

        $fee = $fees->get($this->key($combo['marketplace'], $combo['tier']));

        if (! $fee) {
            $row['error'] = "Biaya untuk {$label} belum diatur sama sekali.";

            return $row;
        }

        // null ≠ 0. Baris yang cuma keisi separuh = belum siap, bukan gratis.
        $missing = $fee->missingFields();

        if ($missing !== []) {
            $row['error'] = ucfirst(implode(', ', $missing))." untuk {$label} belum diisi.";

            return $row;
        }

        $marketplacePercent = $fee->totalPercent();

        // FASE 3 — dihitung SEBELUM guard penyebut: untung gak butuh target margin
        // sama sekali. Jadi walau target margin bikin harga rekomendasi mustahil,
        // angka untung dari harga yang Thomas ketik TETAP sah.
        if ($typedPrice !== null && $typedPrice > 0) {
            $profit = $typedPrice * (1 - ($marketplacePercent + $taxPercent) / 100) - $costAfterProgram;

            $row['evaluation'] = [
                'price'          => $typedPrice,
                'profit'         => (int) round($profit),
                'margin_percent' => round($profit / $typedPrice * 100, 2),
            ];
        }

        $totalPercent = $marketplacePercent + $taxPercent + $marginPercent;
        $denominator  = 1 - ($totalPercent / 100);

        // Edge case wajib: kalau potongan totalnya ≥ 100%, gak ada harga jual yang bisa
        // nutup — mau dijual berapa pun, sisanya gak akan pernah nyampe target.
        if ($denominator <= 0) {
            $row['error'] = 'Gak mungkin dihitung: total potongan '.round($totalPercent, 2)
                .'% (admin + program + pajak + margin) udah makan seluruh harga jual. Turunin target margin atau cek biaya.';

            return $row;
        }

        $raw   = $costAfterProgram / $denominator;
        $price = $this->roundUp($raw);

        $row['price'] = $price;

        // breakdown BUKAN buat ditampilin ke Thomas di form Produk (keputusannya:
        // cuma angka akhir, "belakang layar"). Ini buat tinker/debug + bahan Fase 3.
        $row['breakdown'] = [
            'modal_asli'             => (int) $product->cost_price,
            'modal_after'            => round($costAfterProgram, 2),
            'admin_percent'          => $fee->admin_percent,
            'program_ongkir_percent' => $fee->program_ongkir_percent,
            'program_diskon_percent' => $fee->program_diskon_percent,
            'program_ekstra_percent' => $fee->program_ekstra_percent,
            'pajak_percent'          => $taxPercent,
            'margin_percent'         => $marginPercent,
            'total_potongan'         => round($totalPercent, 2),
            'penyebut'               => round($denominator, 4),
            'harga_sebelum_bulat'    => round($raw, 2),
        ];

        return $row;
    }

    /** Bulat KE ATAS per kelipatan ROUND_TO. */
    protected function roundUp(float $value): int
    {
        return (int) (ceil($value / self::ROUND_TO) * self::ROUND_TO);
    }

    /** Kunci gabungan marketplace+tier — dipakai konsisten di lookup & grouping. */
    protected function key(string $marketplace, ?string $tier): string
    {
        return $marketplace.'|'.($tier ?? '');
    }
}
