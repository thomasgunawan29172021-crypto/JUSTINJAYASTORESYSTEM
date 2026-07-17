<?php

namespace App\Services;

use App\Models\MarketplaceCategoryFee;
use App\Models\PricingSetting;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Mesin harga jual rekomendasi.
 *
 *      P = (M + S) / (1 − a − t − m)
 *
 *   M = modal setelah diskon program (brand, atau override produk)
 *   S = ongkir, nominal Rupiah — per (marketplace × tier × kategori)
 *   a = % biaya admin  — per (marketplace × tier × kategori)
 *   t = % pajak (PPh Final) — global
 *   m = % target margin bersih — global. Margin = untung ÷ HARGA JUAL, bukan ÷ modal.
 *       Rumus ini bentuknya begini justru KARENA itu: margin dari harga jual bikin
 *       harga jual muncul di dua sisi persamaan, jadi harus dipindah ke penyebut.
 *
 * PRINSIP UTAMA: gagal harus BERISIK.
 * Setiap angka yang belum diisi Thomas menghasilkan pesan yang nyebut persis apa yang
 * kurang — bukan diam-diam dianggap nol. Harga yang keliatan valid tapi salah jauh
 * lebih bahaya daripada harga yang gak keluar sama sekali: Thomas gak punya cara tau
 * angkanya ngaco sampai dia rugi di transaksi nyata.
 *
 * Fase 1: service ini belum dipanggil dari mana-mana. Diuji lewat tinker dulu.
 */
class PricingCalculatorService
{
    /** Harga rekomendasi dibulatkan KE ATAS per kelipatan ini (keputusan Thomas). */
    public const ROUND_TO = 1000;

    /**
     * Hitung harga rekomendasi sebuah produk untuk semua kombinasi (marketplace × tier)
     * toko aktif.
     *
     * @return array{blockers: array<int,string>, rows: array<int,array>}
     *   blockers → masalah yang bikin SEMUA kombinasi gak bisa dihitung. Kalau ini
     *              gak kosong, `rows` pasti kosong — percuma ngitung per toko.
     *   rows     → satu entry per kombinasi. `price` int kalau sukses, null kalau
     *              gagal (alasannya di `error`).
     */
    public function calculate(Product $product): array
    {
        $settings = PricingSetting::current();
        $blockers = $this->findBlockers($product, $settings);

        if ($blockers !== []) {
            return ['blockers' => $blockers, 'rows' => []];
        }

        // M dan komponen global dihitung SEKALI di luar loop.
        $costAfterProgram = $product->costAfterProgram();
        $taxPercent       = $settings->tax_percent;
        $marginPercent    = $settings->margin_percent;

        // Prefetch semua baris biaya kategori ini dalam 1 query, di-index pakai kunci
        // gabungan. Tanpa ini, tiap kombinasi = 1 query (N+1) — pola yang udah bikin
        // masalah di AttendanceRecapController dan MarketplaceDashboardController.
        $fees = MarketplaceCategoryFee::where('category_id', $product->category_id)
            ->get()
            ->keyBy(fn (MarketplaceCategoryFee $f) => $this->key($f->marketplace, $f->tier));

        $rows = [];

        foreach ($this->activeCombos() as $combo) {
            $rows[] = $this->calculateCombo(
                $combo, $fees, $costAfterProgram, $taxPercent, $marginPercent, $product
            );
        }

        return ['blockers' => [], 'rows' => $rows];
    }

    /**
     * Masalah yang bikin semua kombinasi mustahil dihitung — dicek duluan supaya
     * Thomas gak dikasih 5 baris error yang isinya keluhan yang sama.
     */
    protected function findBlockers(Product $product, PricingSetting $settings): array
    {
        $blockers = [];

        if ($product->category_id === null) {
            $blockers[] = 'Kategori produk belum dipilih — biaya admin & ongkir gak bisa ditentukan tanpa kategori.';
        }

        if ($settings->margin_percent === null) {
            $blockers[] = 'Target margin belum diset di Pengaturan Harga.';
        }

        if ($settings->tax_percent === null) {
            $blockers[] = 'Pajak belum diset di Pengaturan Harga.';
        }

        if ($product->cost_price <= 0) {
            $blockers[] = 'Modal produk masih 0 — isi harga beli dulu.';
        }

        return $blockers;
    }

    /**
     * Kombinasi (marketplace × tier) yang beneran ada di toko aktif.
     *
     * Sengaja diturunkan dari `stores`, BUKAN dari daftar marketplace yang diketik
     * manual: nilai marketplace & tier di tabel biaya disalin dari sini, jadi
     * lookup-nya gak mungkin meleset gara-gara typo atau beda huruf besar-kecil.
     *
     * Toko di Sampah otomatis kebuang (SoftDeletes di model Store).
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
        Product $product
    ): array {
        $row = [
            'marketplace' => $combo['marketplace'],
            'tier'        => $combo['tier'],
            'store_names' => $combo['store_names'],
            'price'       => null,
            'error'       => null,
            'breakdown'   => null,
        ];

        $label = ucfirst($combo['marketplace']).' '.($combo['tier'] ?? '?')
            .' × '.$product->category->name;

        // Toko tanpa tier gak bisa dicariin biayanya sama sekali.
        if ($combo['tier'] === null) {
            $row['error'] = 'Tier belum diatur untuk toko: '
                .implode(', ', $combo['store_names']).'.';

            return $row;
        }

        $fee = $fees->get($this->key($combo['marketplace'], $combo['tier']));

        if (! $fee) {
            $row['error'] = "Biaya untuk {$label} belum diatur sama sekali.";

            return $row;
        }

        // null ≠ 0. Baris yang cuma keisi separuh = belum siap, bukan gratis.
        $missing = [];
        if ($fee->admin_percent === null) {
            $missing[] = 'biaya admin';
        }
        if ($fee->shipping_cost === null) {
            $missing[] = 'ongkir';
        }

        if ($missing !== []) {
            $row['error'] = ucfirst(implode(' & ', $missing))." untuk {$label} belum diisi.";

            return $row;
        }

        $adminPercent = $fee->admin_percent;
        $totalPercent = $adminPercent + $taxPercent + $marginPercent;
        $denominator  = 1 - ($totalPercent / 100);

        // Edge case wajib: kalau potongan totalnya ≥ 100%, gak ada harga jual yang
        // bisa nutup — mau dijual berapa pun, sisanya gak akan pernah nyampe target.
        // Matematikanya ngasih pembagian nol / harga negatif. Harus ditolak.
        if ($denominator <= 0) {
            $row['error'] = 'Gak mungkin dihitung: admin '.$adminPercent.'% + pajak '
                .$taxPercent.'% + margin '.$marginPercent.'% = '.$totalPercent
                .'%, udah makan seluruh harga jual. Turunin target margin.';

            return $row;
        }

        $raw   = ($costAfterProgram + $fee->shipping_cost) / $denominator;
        $price = $this->roundUp($raw);

        $row['price'] = $price;

        // Breakdown BUKAN buat ditampilin ke Thomas di form Produk (keputusannya:
        // cuma angka akhir, "belakang layar"). Ini buat tinker/debug sekarang, dan
        // bahan Fase 3 (estimasi untung/rugi). View yang mutusin nampilin atau nggak.
        $row['breakdown'] = [
            'modal_asli'        => (int) $product->cost_price,
            'program_percent'   => $product->effectiveProgramDiscount(),
            'modal_after'       => round($costAfterProgram, 2),
            'ongkir'            => $fee->shipping_cost,
            'admin_percent'     => $adminPercent,
            'pajak_percent'     => $taxPercent,
            'margin_percent'    => $marginPercent,
            'penyebut'          => round($denominator, 4),
            'harga_sebelum_bulat' => round($raw, 2),
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