<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Gaji pokok bulanan (Rp). Hanya CEO yang lihat/ubah (via User Management).
            $table->unsignedBigInteger('base_salary')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('base_salary');
        });
    }
};