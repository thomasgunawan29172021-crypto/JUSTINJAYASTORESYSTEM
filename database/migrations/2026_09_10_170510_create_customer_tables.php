<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Customer/CRM. customers = data inti pelanggan.
     * customer_contacts TERPISAH dari customers karena satu pelanggan boleh
     * punya >1 nomor + channel lain (email dll) — keputusan Q7.
     * customer_histories = jejak audit manual, kembaran pola *_histories
     * yang sudah dipakai di ServiceTicket/WarrantyClaim.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('address', 255)->nullable();
            // Bebas isi: walk-in, referral, tiktok, instagram, dll.
            // Shopee sengaja gak dijadiin opsi tetap karena data pelanggan
            // Shopee memang gak pernah bisa masuk ke CRM ini (keputusan awal).
            $table->string('source', 50)->nullable();
            $table->foreignId('branch_id')->constrained(); // cabang daftar pertama
            $table->foreignId('created_by')->constrained('users'); // staff yang input
            $table->text('notes')->nullable();
            $table->timestamps(); // created_at = tanggal daftar pertama (otomatis)
            $table->softDeletes();

            $table->index('name'); // dipakai search & dedup
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // phone, whatsapp, email, other
            // Buat phone/whatsapp: dinormalisasi 62xxx (lihat Customer::normalizePhone()).
            $table->string('value', 150);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['type', 'value']); // dipakai buat search & dedup exact-match
        });

        Schema::create('customer_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 20); // created, updated, deleted
            $table->json('changes')->nullable(); // diff before/after, buat 'updated'
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_histories');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};