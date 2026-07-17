<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // Default program (diskon/subsidi supplier) untuk semua produk brand ini.
            // nullable + dianggap 0 sama calculator — bukan NOT NULL, karena form yang
            // dikosongin ngirim '' dan Laravel nge-cast '' jadi null. Pola bug yang udah
            // 3x kejadian di project ini (price_grosir, base_salary, field produk).
            $table->decimal('program_discount_percent', 5, 2)->nullable()->after('name');
        });

        Schema::table('products', function (Blueprint $table) {
            // restrictOnDelete (bukan nullOnDelete): hapus kategori yang masih dipake
            // produk harus DITOLAK dengan pesan jelas, bukan diem-diem ngosongin
            // category_id sampai harga rekomendasi produknya ilang tanpa sebab.
            // Controller kategori wajib cek dependensi dulu — pola yang sama kayak
            // BranchSettingController::destroy().
            $table->foreignId('category_id')->nullable()->after('brand_id')
                  ->constrained()->restrictOnDelete();

            // Override program per-produk. PENTING — null ≠ 0 di kolom ini:
            //   null → ikut default brand
            //   0    → produk ini memang gak dapet program sama sekali
            // Jangan pernah pake ?? 0 atau ->default(0) di sini, itu ngilangin bedanya.
            $table->decimal('program_discount_percent', 5, 2)->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'program_discount_percent']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('program_discount_percent');
        });
    }
};
