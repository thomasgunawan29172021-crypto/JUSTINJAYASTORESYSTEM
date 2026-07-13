<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Contoh: Cabang Pusat
            $table->string('code', 10)->unique();   // Contoh: PST, ILR, KM9 — dipakai di nomor servis
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable(); // Nomor WA cabang untuk footer notifikasi
            $table->boolean('has_service')->default(true); // Cabang yang menerima servis
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
