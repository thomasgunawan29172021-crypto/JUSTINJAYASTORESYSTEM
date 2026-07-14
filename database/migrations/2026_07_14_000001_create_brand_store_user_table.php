<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PIC per brand PER TOKO (revisi Thomas): Justin pegang Soraspace di Apex,
        // Revin pegang Soraspace di toko lain. 1 brand+toko = 1 PIC (unique) — "tidak double".
        // brand_user lama TETAP ADA sebagai turunan (di-sync dari tabel ini) sampai
        // task engine dipindah ke scope baru (Tahap B).
        Schema::create('brand_store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['brand_id', 'store_id']); // kunci anti-double
            $table->index('user_id');
        });

        // Migrasi data lama: PIC per-brand → PIC di SEMUA toko target brand-nya.
        // Kalau satu brand punya >1 PIC lama, PIC PERTAMA yang dipakai (unique constraint) —
        // Thomas tinggal bagi ulang lewat UI baru.
        foreach (\App\Models\Brand::withTrashed()->with(['stores', 'pics'])->get() as $brand) {
            $pic = $brand->pics->first();
            if (! $pic) continue;

            foreach ($brand->stores as $store) {
                DB::table('brand_store_user')->insertOrIgnore([
                    'brand_id' => $brand->id,
                    'store_id' => $store->id,
                    'user_id'  => $pic->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_store_user');
    }
};
