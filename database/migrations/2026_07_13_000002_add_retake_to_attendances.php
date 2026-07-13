<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Permintaan foto ulang oleh CEO. Foto ulang disimpan dengan '-retake-' di path (penanda).
            $table->boolean('retake_in_requested')->default(false)->after('clock_in_photo');
            $table->boolean('retake_out_requested')->default(false)->after('clock_out_photo');
            $table->string('retake_reason', 300)->nullable()->after('retake_out_requested');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['retake_in_requested', 'retake_out_requested', 'retake_reason']);
        });
    }
};
