<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SNAPSHOT slip gaji — sekali terbit, angka beku.
        // Perubahan base_salary / status hari SETELAH terbit tidak mengubah slip.
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->char('period', 7);                        // '2026-07' — bulan yang digaji
            $table->unsignedBigInteger('base_salary');        // gaji pokok saat terbit
            $table->unsignedSmallInteger('workdays');         // pembagi: hari kalender − hari off
            $table->unsignedBigInteger('daily_rate');         // base_salary / workdays (dibulatkan)
            $table->unsignedSmallInteger('deducted_days');    // alpha + izin dipotong
            $table->unsignedBigInteger('deduction_amount');   // daily_rate × deducted_days
            $table->unsignedBigInteger('net_salary');         // base_salary − deduction_amount
            $table->json('day_statuses');                     // snapshot status per tanggal (arsip bukti)
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('issued_at');

            $table->unique(['user_id', 'period']);            // 1 slip per orang per bulan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};