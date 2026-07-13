<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();      // SV-PST-2607-0042
            $table->string('tracking_token', 40)->unique();     // Untuk link QR langsung (tanpa input manual)
            $table->foreignId('branch_id')->constrained('branches');

            // Customer
            $table->string('customer_name');
            $table->string('customer_phone', 30)->index();      // Disimpan ternormalisasi: 628xxxx
            $table->string('customer_phone_alt', 30)->nullable();

            // Unit
            $table->string('device_brand', 50);
            $table->string('device_model', 100);
            $table->string('imei', 40)->nullable();
            $table->text('device_passcode')->nullable();        // Dienkripsi via cast di model
            $table->text('complaint');                          // Keluhan customer
            $table->json('physical_condition')->nullable();     // Checklist kondisi fisik saat masuk
            $table->json('accessories')->nullable();            // Kelengkapan: sim, memory, case, charger

            // Alur & penanggung jawab
            $table->string('status', 30)->default('diterima')->index();
            $table->foreignId('created_by')->constrained('users');          // Frontliner intake
            $table->foreignId('technician_id')->nullable()->constrained('users');
            $table->foreignId('admin_id')->nullable()->constrained('users'); // Admin chat penanggung jawab

            // Diagnosa & biaya (operasional saja — akuntansi tetap di Accurate)
            $table->text('diagnosis')->nullable();
            $table->unsignedBigInteger('estimated_cost')->nullable();
            $table->unsignedBigInteger('approved_cost')->nullable();
            $table->unsignedBigInteger('final_cost')->nullable();

            // Tanggal-tanggal kunci
            $table->timestamp('checked_in_at');                 // Tanggal masuk servis
            $table->date('estimated_done_at')->nullable();      // Estimasi selesai (dijanjikan ke customer)
            $table->timestamp('completed_at')->nullable();      // Lolos QC / siap diambil
            $table->timestamp('notified_at')->nullable();       // Customer dikabari "siap diambil"
            $table->timestamp('checked_out_at')->nullable();    // Tanggal keluar (diambil/dikirim)

            // Garansi servis
            $table->unsignedSmallInteger('warranty_days')->default(30);
            $table->date('warranty_until')->nullable();
            $table->foreignId('parent_ticket_id')->nullable()->constrained('service_tickets'); // Klaim garansi merujuk tiket asal

            $table->text('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('checked_in_at');
            $table->index('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
