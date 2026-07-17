<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master kategori produk — dikelola CEO sendiri di halaman Pengaturan Harga.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Biaya per kombinasi (marketplace × tier toko × kategori).
        //
        // marketplace & tier SENGAJA string, bukan FK ke tabel master:
        // stores.marketplace dan stores.tier juga string polos, dan nilai di sini
        // DISALIN dari sana (bukan diketik ulang manusia) supaya lookup tidak pernah
        // meleset gara-gara typo/beda huruf besar-kecil.
        Schema::create('marketplace_category_fees', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 50);
            $table->string('tier', 30);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // nullable = "Thomas belum ngisi" — INI BUKAN NOL.
            // Calculator WAJIB bedain: null → tolak hitung + sebutin apa yang kurang;
            // 0 → memang beneran gratis. Kalau disamain, Thomas dapet harga yang
            // keliatan valid padahal salah, dan gak akan pernah tau.
            $table->decimal('admin_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('shipping_cost')->nullable();

            $table->timestamps();

            $table->unique(['marketplace', 'tier', 'category_id'], 'mcf_unique');
        });

        // Singleton — selalu 1 baris, gak ada create/delete di UI.
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('tax_percent', 5, 2)->nullable();     // PPh Final, % dari omzet
            $table->decimal('margin_percent', 5, 2)->nullable();  // target untung bersih ÷ HARGA JUAL
            $table->timestamps();
        });

        DB::table('pricing_settings')->insert([
            'tax_percent'    => 0.50,
            // Sengaja dibiarkan kosong: Thomas HARUS set sendiri sebelum engine jalan.
            // Kalau dikasih default ngasal, dia gak akan sadar angkanya bukan angka dia.
            'margin_percent' => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
        Schema::dropIfExists('marketplace_category_fees');
        Schema::dropIfExists('categories');
    }
};
