<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toko = akun di marketplace (mis. "JJ Official" di Shopee, mall)
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('marketplace', 50);        // lowercase: shopee, tiktok, ...
            $table->boolean('is_mall')->default(false); // menentukan harga mall / non-mall yang dipakai
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // PIC per toko — boleh lebih dari satu orang
        Schema::create('store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['store_id', 'user_id']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Pemetaan aturan Thomas: brand X diposting ke toko mana saja
        Schema::create('brand_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unique(['brand_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_store');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('stores');
    }
};
