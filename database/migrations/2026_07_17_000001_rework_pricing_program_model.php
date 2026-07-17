<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rework model program & biaya (keputusan Thomas, Fase 3.5).
     *
     * 1. Ongkir nominal DIBUANG — diganti 3 biaya layanan marketplace, semuanya persen
     *    (Gratis Ongkir XTRA, Promo XTRA, dst). Rumus berubah: S hilang dari pembilang,
     *    semua potongan pindah ke penyebut.
     *
     *    ⚠️ DATA HILANG PERMANEN: isi shipping_cost gak bisa dikonversi ke persen —
     *    Rp 3.000 itu berapa persen tergantung harga produknya. Thomas WAJIB isi ulang.
     *    down() bikin kolomnya balik, TAPI ISINYA TETAP KOSONG.
     *
     * 2. Program brand jadi 2 tingkat: potong depan & potong belakang, dihitung
     *    BERTINGKAT (belakang dari sisa setelah depan). Kolom lama di-rename, bukan
     *    dibuang — isinya selamat.
     *
     * 3. Produk: program berubah dari OVERRIDE jadi TAMBAHAN, plus kolom nominal.
     *
     *    ⚠️ ARTI DATA LAMA BERUBAH: products.program_discount_percent dulu berarti
     *    "pakai angka ini, abaikan brand". Sekarang jadi "tambahan di atas brand".
     *    Produk yang terlanjur diisi bakal kepotong lebih dalam dari yang dimaksud.
     *    Cek isinya sebelum migrate — di production kolom ini baru ada sejak hari ini.
     */
    public function up(): void
    {
        Schema::table('marketplace_category_fees', function (Blueprint $table) {
            $table->dropColumn('shipping_cost');
        });

        Schema::table('marketplace_category_fees', function (Blueprint $table) {
            // nullable = "belum diisi" — BUKAN nol. 0 = ikut program tapi gratis /
            // gak ikut program sama sekali. Calculator wajib bedain keduanya.
            $table->decimal('program_ongkir_percent', 5, 2)->nullable()->after('admin_percent');
            $table->decimal('program_diskon_percent', 5, 2)->nullable()->after('program_ongkir_percent');
            $table->decimal('program_ekstra_percent', 5, 2)->nullable()->after('program_diskon_percent');
        });

        // rename dipisah dari add — beberapa driver gak suka dua operasi ini sekaligus
        Schema::table('brands', function (Blueprint $table) {
            $table->renameColumn('program_discount_percent', 'program_front_percent');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->decimal('program_back_percent', 5, 2)->nullable()->after('program_front_percent');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('program_discount_percent', 'program_extra_percent');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('program_extra_amount')->nullable()->after('program_extra_percent');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('program_extra_amount');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('program_extra_percent', 'program_discount_percent');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('program_back_percent');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->renameColumn('program_front_percent', 'program_discount_percent');
        });

        Schema::table('marketplace_category_fees', function (Blueprint $table) {
            $table->dropColumn(['program_ongkir_percent', 'program_diskon_percent', 'program_ekstra_percent']);
        });

        Schema::table('marketplace_category_fees', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_cost')->nullable()->after('admin_percent');
        });
    }
};
