<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1) SELAMATKAN konteks produk ke nama diskon SEBELUM product_id dibuang.
        //    "Promo 22" → "Promo 22 (ROBOT TEST 123)". Sekali jalan, teks saja.
        foreach (DB::table('product_discounts')->whereNotNull('product_id')->get() as $row) {
            $productName = DB::table('products')->where('id', $row->product_id)->value('name');
            if ($productName) {
                DB::table('product_discounts')->where('id', $row->id)
                    ->update(['name' => Str::limit($row->name.' ('.$productName.')', 150, '')]);
            }
        }

        // 2) Diskon kini BERDIRI SENDIRI (keputusan Thomas): murni pengingat, tak
        //    terikat produk, tapi punya relasi ke toko. Nama tabel dipertahankan
        //    supaya tidak menambah titik pecah — walau namanya jadi kurang tepat.
        Schema::table('product_discounts', function (Blueprint $table) {
            $table->string('type', 20)->default('promo_toko')->after('name');
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->change();
        });

        // 3) Data lama date-only: "berakhir 15 Jul" = berlaku sampai AKHIR hari itu.
        //    Tanpa ini semua diskon lama mendadak berakhir jam 00:00.
        DB::statement("UPDATE product_discounts SET ends_at = DATE_ADD(DATE(ends_at), INTERVAL '23:59:59' HOUR_SECOND)");

        Schema::table('product_discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });

        // 4) Relasi toko — satu diskon boleh di banyak toko.
        Schema::create('discount_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_discount_id', 'store_id']);
        });
    }

    public function down(): void
    {
        // CATATAN: product_id tidak bisa dipulihkan — datanya sudah tak ada.
        Schema::dropIfExists('discount_store');
        Schema::table('product_discounts', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->date('starts_at')->change();
            $table->date('ends_at')->change();
            $table->dropColumn('type');
        });
    }
};
