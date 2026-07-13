<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal kerja per orang: jam masuk, jam pulang, 1 hari off per minggu.
        // Hanya CEO yang boleh atur (sesuai keputusan Thomas).
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->time('clock_in_time');
            $table->time('clock_out_time');
            $table->unsignedTinyInteger('off_day'); // 0=Minggu ... 6=Sabtu (ikut Carbon dayOfWeek)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};