<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier toko (biasa / star / mall / dst) — biaya admin marketplace beda per tier,
     * dan is_mall yang boolean gak sanggup nampung tier ke-3.
     *
     * CATATAN UTANG TEKNIS — BACA SEBELUM FASE 2:
     * Setelah migration ini, `tier` dan `is_mall` sama-sama ada dan sama-sama
     * ngedeskripsiin hal yang sama. `tier` = SUMBER KEBENARAN. `is_mall` = DEPRECATED,
     * cuma dibiarkan hidup karena product_prices (price_mall/price_regular) dan
     * Product::priceForStore() masih gantung ke situ.
     *
     * Ini SENGAJA, bukan kecerobohan: mecah price_regular jadi harga-per-tier butuh
     * jawaban Thomas per baris ("regular ini dulu maksudnya biasa atau star?"), dan
     * dia baru bisa jawab setelah tiap toko punya tier — yaitu setelah migration ini.
     *
     * FASE 2 WAJIB: restructure product_prices ke baris-per-tier, lalu BUANG is_mall.
     * Selama dua kolom ini hidup bareng, ada risiko drift kalau ada yang ngedit satu
     * tanpa yang lain. Form Toko harus set dua-duanya sampai is_mall dibuang.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('tier', 30)->nullable()->after('marketplace');
        });

        // Backfill dari is_mall. 'biasa' cuma TEBAKAN AWAL — toko non-mall bisa aja
        // sebenernya Star. Thomas wajib benerin manual di form Toko (jumlah toko dikit).
        // Sengaja gak pake ->whereNull('deleted_at'): toko di Sampah pun ikut kebackfill,
        // biar kalau di-restore nanti gak muncul dengan tier kosong.
        DB::table('stores')->where('is_mall', true)->update(['tier' => 'mall']);
        DB::table('stores')->where('is_mall', false)->update(['tier' => 'biasa']);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
