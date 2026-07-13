<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sumber kebenaran "produk X SUDAH terposting di toko Y"
        Schema::create('postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users'); // null = input mundur (backfill)
            $table->timestamp('posted_at');

            $table->unique(['product_id', 'store_id']);
        });

        // Antrian kerja tim posting. Tipe revisi menyusul di M3.
        Schema::create('marketplace_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);                    // posting | price_update
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | done
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();

            $table->index(['store_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_tasks');
        Schema::dropIfExists('postings');
    }
};
