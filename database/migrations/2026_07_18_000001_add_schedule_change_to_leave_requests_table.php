<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Jam masuk/pulang — cuma dipakai tipe ganti_jadwal, null buat sisanya.
            // TIME (bukan datetime) biar konsisten dgn work_schedule_days.clock_in_time:
            // Eloquent balikin 'HH:MM:SS' string, persis yang dipakai resolver & clock-in.
            $table->time('start_time')->nullable()->after('date_to');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
