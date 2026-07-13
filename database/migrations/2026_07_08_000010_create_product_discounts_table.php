<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengingat diskon produk — TIDAK mengubah harga & tidak membuat tugas (keputusan Thomas).
        Schema::create('product_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);          // mis. "Promo 7.7", "Diskon Lebaran"
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
