<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua kolom kekecilan buat data nyata (dua-duanya ketangkep dari log production):
     *
     * 1. products.name — nama produk marketplace (contoh nyata: docking station
     *    Vention dengan semua port ditulis) tembus 200. Naik ke 500.
     *    Aman buat unique index: tabelnya ROW_FORMAT=Dynamic → batas 3072 byte,
     *    500 char × 4 byte (utf8mb4) = 2000 byte. Validasi form ikut dinaikkan
     *    di ProductController — kalau cuma DB-nya, max:200 tetap nolak duluan.
     *
     * 2. social_video_platform.url — link share dari APLIKASI TikTok bawa ekor
     *    tracking 900+ char. Kolomnya SUDAH varchar(500) (bukan 255).
     *
     *    TIDAK dijadiin TEXT: kolom ini punya UNIQUE index penuh
     *    (social_video_platform_url_unique), dan MySQL nolak unique index penuh
     *    di kolom TEXT — "BLOB/TEXT column used in key specification without a
     *    key length". Jadi dinaikin ke 700 char (2800 byte, masih di bawah 3072)
     *    supaya unique-nya tetap utuh.
     *
     *    Obat utamanya BUKAN di sini tapi di VideoController::cleanShareUrl():
     *    ekor tracking dibuang sebelum disimpan, jadi URL TikTok balik ~55 char.
     *    Itu juga yang bikin unique index tetap bermakna — tanpa pembersihan,
     *    video yang sama di-share dua kali menghasilkan dua string beda dan
     *    lolos dari pengecekan duplikat.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 500)->change();
        });

        Schema::table('social_video_platform', function (Blueprint $table) {
            $table->string('url', 700)->change();
        });
    }

    public function down(): void
    {
        // ⚠️ Turun lagi cuma aman kalau belum ada data yang lebih panjang dari batas lama.
        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 200)->change();
        });

        Schema::table('social_video_platform', function (Blueprint $table) {
            $table->string('url', 500)->change();
        });
    }
};
