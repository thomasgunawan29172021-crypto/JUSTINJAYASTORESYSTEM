<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Checklist "kabari customer" — pengganti notif WA otomatis.
        // Satu baris = satu jenis kabar yang SUDAH dikirim staf lewat chat internal.
        Schema::create('ticket_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->string('type', 30);            // dicek | harga | selesai
            $table->foreignId('user_id')->nullable()->constrained('users'); // siapa yang centang
            $table->timestamp('created_at');       // kapan dicentang → dipakai KPI jeda kabari

            $table->unique(['ticket_id', 'type']); // tiap jenis cuma bisa dicentang 1x per tiket
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notifications');
    }
};