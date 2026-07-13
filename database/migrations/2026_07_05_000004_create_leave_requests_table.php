<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);                    // izin | sakit | cuti
            $table->date('date_from');
            $table->date('date_to');
            $table->text('reason');
            $table->string('attachment_path')->nullable(); // wajib untuk sakit (surat dokter)

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->boolean('is_paid')->nullable();           // null selama pending; diisi saat diputuskan
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};