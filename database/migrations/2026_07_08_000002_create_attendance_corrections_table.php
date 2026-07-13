<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail koreksi absen. Satu baris = satu tindakan koreksi CEO.
        // before = null artinya absen dibuat manual (tidak ada data sebelumnya).
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('corrected_by')->constrained('users');
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
