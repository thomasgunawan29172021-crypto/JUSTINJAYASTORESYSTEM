<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setiap perubahan status = 1 baris. Ini sumber tunggal semua KPI durasi.
        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable(); // null = tiket baru dibuat
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained('users'); // null = aksi sistem
            $table->text('note')->nullable();
            $table->timestamp('created_at');

            $table->index(['ticket_id', 'to_status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_histories');
    }
};
