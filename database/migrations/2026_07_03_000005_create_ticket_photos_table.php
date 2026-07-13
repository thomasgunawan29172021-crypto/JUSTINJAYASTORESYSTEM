<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->string('type', 20)->default('intake'); // intake | qc | other
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_photos');
    }
};
