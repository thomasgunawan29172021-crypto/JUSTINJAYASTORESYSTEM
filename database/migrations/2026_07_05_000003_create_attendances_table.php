<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');

            $table->timestamp('clock_in_at');
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_distance_m')->nullable(); // jarak dari cabang saat absen
            $table->string('clock_in_photo')->nullable();               // nullable: foto dihapus setelah 45 hari, record tetap

            $table->unsignedSmallInteger('late_minutes')->default(0);   // menit MENTAH lewat jadwal; toleransi 5 mnt dihitung di model

            $table->timestamp('clock_out_at')->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('clock_out_distance_m')->nullable();
            $table->string('clock_out_photo')->nullable();

            $table->boolean('auto_closed')->default(false); // ditutup sistem 23:49 — tanda "perlu review CEO"
            $table->boolean('is_off_day')->default(false);  // absen di hari off-nya sendiri (kerja sukarela)
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);       // aturan Thomas: 1x absen per hari
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};