<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();      // unique → import CSV idempotent
            $table->foreignId('brand_id')->constrained('brands');
            $table->unsignedBigInteger('cost_price')->default(0);    // harga beli — RAHASIA, CEO only
            $table->unsignedBigInteger('price_offline')->default(0);
            $table->unsignedBigInteger('price_grosir')->default(0);
            $table->timestamp('archived_at')->nullable();            // arsip = discontinue, bukan hapus
            $table->foreignId('replacement_product_id')->nullable()
                  ->constrained('products')->nullOnDelete();         // produk pengganti (kalau ada)
            $table->timestamps();
        });

        // Harga per marketplace: mall & non-mall (keputusan Thomas)
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace', 50);           // lowercase, match stores.marketplace
            $table->unsignedBigInteger('price_mall')->nullable();
            $table->unsignedBigInteger('price_regular')->nullable();
            $table->unique(['product_id', 'marketplace']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
    }
};
