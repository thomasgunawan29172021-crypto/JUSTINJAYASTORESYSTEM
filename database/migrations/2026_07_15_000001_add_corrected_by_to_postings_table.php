<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postings', function (Blueprint $table) {
            // Siapa yang MENCENTANG lewat koreksi manual (beda dari posted_by yang sengaja
            // dikosongkan biar tidak dikreditkan ke metrik PIC). Murni jejak audit.
            $table->foreignId('corrected_by')->nullable()->after('posted_by')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_by');
        });
    }
};
