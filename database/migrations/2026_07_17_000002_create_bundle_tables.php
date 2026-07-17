<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bundle = baris di `products` dengan is_bundle=true + komponennya di pivot.
     * Sengaja numpang tabel products, bukan tabel sendiri: harga per marketplace,
     * tugas posting, diskon, sampah — semua jalan apa adanya tanpa dikembarin.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('brand_id');
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();

            // Bundle dibuang permanen → komponennya ikut. Soft delete TIDAK memicu
            // cascade (Laravel cuma isi deleted_at), jadi bundle di Sampah tetap utuh
            // dan bisa dipulihkan lengkap.
            $table->foreignId('bundle_id')->constrained('products')->cascadeOnDelete();

            // restrictOnDelete: produk yang masih jadi komponen bundle GAK BOLEH
            // dihapus permanen. ProductController::clearTrash() udah nangkep
            // QueryException dan nge-skip — jadi produknya cuma dilewati, bukan bikin
            // halaman error. Kalau nullOnDelete, bundle diam-diam kehilangan komponen
            // dan modalnya turun tanpa ada yang tau.
            $table->foreignId('component_id')->constrained('products')->restrictOnDelete();

            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            $table->unique(['bundle_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_bundle');
        });
    }
};
