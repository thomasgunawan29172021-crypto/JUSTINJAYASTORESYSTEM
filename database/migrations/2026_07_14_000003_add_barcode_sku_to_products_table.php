<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->after('name');  // EAN/UPC barang fisik — opsional
            $table->string('sku', 100)->nullable()->after('barcode');   // kode internal — opsional

            $table->index('barcode');   // dicari dari kotak Cari (scan barang)
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['sku']);
            $table->dropColumn(['barcode', 'sku']);
        });
    }
};
