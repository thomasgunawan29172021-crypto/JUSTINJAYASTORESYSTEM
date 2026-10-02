<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('waiting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waiting_order_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 150);
            $table->string('product_key', 150)->index();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->string('status', 20)->default('waiting')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_items');
        Schema::dropIfExists('waiting_orders');
    }
};
