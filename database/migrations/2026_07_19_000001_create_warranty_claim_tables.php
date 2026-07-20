<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul klaim garansi/retur — kembaran pola tiket servis:
     * nomor unik + token lacak publik + riwayat per transisi.
     *
     * Beda dari servis: alur URUT KETAT (cuma maju +1), dengan DUA pengecualian
     * yang diputusin Thomas:
     *   - batal: boleh dari tahap mana pun SEBELUM barang sampai supplier
     *   - hasil vendor (diterima/ditolak) = KOLOM, bukan cabang alur — barang
     *     ditolak pun tetap jalan ke dikirim-balik → siap-diambil → selesai
     */
    public function up(): void
    {
        // Master vendor (distributor / supplier / service center) — keputusan #14.
        // Master data biar nanti bisa direkap "klaim ke vendor X rata-rata berapa lama".
        Schema::create('warranty_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 30)->unique();   // RT-{CABANG}-{YYMM}-{urut}
            // unique() menyamakan dgn service_tickets.tracking_token: ini kolom yang
            // di-query dari halaman lacak PUBLIK, jadi butuh index (tanpa itu tiap
            // pelanggan yang buka link = full table scan) sekaligus jaminan anti-tabrakan.
            $table->string('tracking_token', 64)->unique(); // lacak publik via QR, kembaran servis

            $table->foreignId('branch_id')->constrained();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 30);           // dinormalisasi 62xxx, kembaran servis

            // Produk WAJIB dari master (keputusan #5: semua kedaftar, gak ada ketik bebas).
            // restrictOnDelete: produk yang punya klaim gak boleh dihapus permanen —
            // klaim itu jejak hukum ke pelanggan.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('imei', 50)->nullable();         // #6 — perlu buat HP, aksesoris kosongin

            $table->string('order_number', 100)->nullable(); // no pesanan / no nota
            $table->date('purchased_at')->nullable();        // #8 — tanggal beli terpisah
            $table->json('completeness')->nullable();        // #7 — checklist, pola accessories servis
            $table->text('reason');                          // alasan retur

            // nullable → vendor baru keisi pas tahap dikirim_vendor. Klaim yang
            // dibatalin sebelum kirim gak pernah punya vendor.
            $table->foreignId('vendor_id')->nullable()->constrained('warranty_vendors')->nullOnDelete();

            $table->string('status', 30);

            // Hasil cek vendor — KOLOM, bukan status. null sampai tahap hasil_vendor.
            $table->string('outcome', 20)->nullable();      // 'diterima' / 'ditolak'
            $table->text('outcome_note')->nullable();       // hasil cek / rekomendasi vendor

            $table->text('cancel_reason')->nullable();

            // Follow-up terakhir — dasar reset timer SLA (kuning 7 hari, merah 14).
            // Detail per-followup ada di histories; ini denormalisasi biar query
            // dashboard gak perlu join.
            $table->timestamp('last_followed_up_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();   // masuk siap_diambil
            $table->timestamp('picked_up_at')->nullable();   // selesai / diambil
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warranty_claim_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('warranty_claims')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();    // null = entri follow-up (bukan transisi)
            $table->boolean('is_followup')->default(false);
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('warranty_claim_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('warranty_claims')->cascadeOnDelete();
            $table->string('path');
            // #9 — foto barang segala sisi PAS INTAKE + bukti pengiriman PAS KIRIM.
            // Dibedain type biar halaman lacak bisa misahin "kondisi barang" vs "resi".
            $table->string('type', 20)->default('intake');  // 'intake' / 'shipping'
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_photos');
        Schema::dropIfExists('warranty_claim_histories');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranty_vendors');
    }
};
