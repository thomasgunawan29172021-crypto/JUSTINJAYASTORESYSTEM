<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan operasional sparepart per tiket, untuk hitung margin servis.
        // BUKAN pengganti Accurate — pencatatan akuntansi tetap satu pintu di Accurate.
        Schema::create('ticket_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedBigInteger('cost')->default(0);  // Modal per unit
            $table->unsignedBigInteger('price')->default(0); // Harga jual per unit ke customer
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_parts');
    }
};
