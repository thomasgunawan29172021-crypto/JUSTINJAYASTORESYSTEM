<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            // Tanggal mulai dihitung absensinya. Sebelum ini: tidak dievaluasi
            // (bukan alpha) — mencegah potongan tidak adil untuk hari sebelum go-live/karyawan masuk.
            $table->date('effective_from')->nullable()->after('off_day');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('effective_from');
        });
    }
};
