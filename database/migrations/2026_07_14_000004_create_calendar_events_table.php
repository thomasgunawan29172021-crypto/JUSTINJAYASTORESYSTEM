<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event MANUAL kalender (payroll, pajak, deadline). Event otomatis
        // (cuti, video due, diskon) dihitung on-the-fly — tidak disimpan di sini.
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->date('date');
            $table->date('date_end')->nullable();      // null = 1 hari
            $table->string('color', 20)->default('slate'); // slate|emerald|amber|rose|violet|sky
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};