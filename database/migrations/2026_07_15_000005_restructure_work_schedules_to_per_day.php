<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Tabel anak: jadwal PER HARI (0=Minggu..6=Sabtu, pola Carbon::dayOfWeek).
        //    Jam kosong (null) = hari itu libur — menggantikan off_day tunggal.
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('clock_in_time')->nullable();
            $table->time('clock_out_time')->nullable();

            $table->unique(['work_schedule_id', 'day_of_week']);
        });

        // 2) Migrasi jadwal lama → 7 baris per orang: 6 hari pakai jam lama,
        //    1 hari (bekas off_day) jam kosong. Nol kerja manual buat Thomas.
        $old  = DB::table('work_schedules')->get();
        $rows = [];
        foreach ($old as $sched) {
            foreach (range(0, 6) as $dow) {
                $wasOff = $dow === (int) $sched->off_day;
                $rows[] = [
                    'work_schedule_id' => $sched->id,
                    'day_of_week'      => $dow,
                    'clock_in_time'    => $wasOff ? null : $sched->clock_in_time,
                    'clock_out_time'   => $wasOff ? null : $sched->clock_out_time,
                ];
            }
        }
        if ($rows) {
            DB::table('work_schedule_days')->insert($rows);
        }

        // 3) Kolom lama tak dipakai lagi — SEMUA kode konsumen di-update bareng
        //    migration ini, jadi aman dibuang sekarang (bukan dibiarkan basi).
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['clock_in_time', 'clock_out_time', 'off_day']);
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->time('clock_in_time')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->unsignedTinyInteger('off_day')->nullable();
        });
        // CATATAN: rollback TIDAK merekonstruksi nilai lama otomatis.
        Schema::dropIfExists('work_schedule_days');
    }
};