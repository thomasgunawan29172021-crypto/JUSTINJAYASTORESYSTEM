<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DUA SISTEM RETUR (keputusan Thomas, Agustus 2026).
     *
     * Sebelumnya semua brand dianggap sama: barang WAJIB dikirim ke supplier
     * dulu, baru pelanggan dapat gantinya. Itu benar buat Ugreen/Anker, tapi
     * salah buat Robot/Olike — di sana barang pelanggan langsung kita ganti,
     * dan urusan klaim ke suppliernya jalan belakangan sebagai perkara kita
     * sendiri. Jadi sekarang ada dua sumbu yang TERPISAH:
     *
     *   1. Alur pelanggan  — yang kelihatan di halaman lacak. Tiga varian,
     *      lihat App\Enums\WarrantyClaimFlow.
     *   2. Klaim ke supplier — internal, TIDAK PERNAH muncul di lacak publik.
     *      Berbentuk pengiriman berisi banyak unit sekaligus, karena nyatanya
     *      unit dikumpulkan dulu baru dikirim borongan ke distributor.
     *
     * Mode dibaca dari brand produk: brand nunjuk vendor, vendor punya flag
     * requires_send_first. Disimpan sebagai SNAPSHOT di kolom claims.flow —
     * kalau nanti setelan vendor diubah, klaim yang sudah jalan tidak boleh
     * ikut berubah alurnya di tengah jalan.
     */
    public function up(): void
    {
        // --- 1. Vendor: penentu mode retur (pilihan di halaman Vendor Retur) ---
        Schema::table('warranty_vendors', function (Blueprint $table) {
            // default true = perilaku lama. Vendor yang sudah ada tetap "wajib
            // kirim dahulu", jadi tidak ada klaim berjalan yang berubah artinya.
            $table->boolean('requires_send_first')->default(true)->after('phone');
        });

        // --- 2. Brand nunjuk ke vendor returnya ---
        Schema::table('brands', function (Blueprint $table) {
            // nullOnDelete: vendor dihapus ≠ brand ikut hilang. Brand tanpa
            // vendor jatuh ke mode paling aman (wajib kirim dahulu).
            $table->foreignId('warranty_vendor_id')->nullable()->after('name')
                  ->constrained('warranty_vendors')->nullOnDelete();
        });

        // --- 3. Klaim: alur + jejak antrean klaim supplier + jejak "sudah dikabari" ---
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->string('flow', 20)->default('kirim_dulu')->after('status');

            // Kapan unit ini dinyatakan siap diklaim ke supplier (masuk antrean).
            // null = belum masuk antrean. Diisi otomatis di tahap yang sesuai
            // per alur, atau manual lewat tombol "tetap klaim ke supplier".
            $table->timestamp('supplier_queued_at')->nullable()->after('picked_up_at');

            // Diputuskan TIDAK diklaim (rugi ditanggung sendiri). Dipisah dari
            // queued biar antrean tidak menggembung isi unit yang memang
            // sengaja dilepas — antrean yang penuh sampah bakal diabaikan orang.
            $table->timestamp('supplier_skipped_at')->nullable()->after('supplier_queued_at');
            $table->text('supplier_note')->nullable()->after('supplier_skipped_at');

            // Admin chat: jejak pelanggan sudah dihubungi. Detail per kabar ada
            // di histories (is_notify); ini denormalisasi buat penanda di daftar.
            $table->timestamp('last_notified_at')->nullable()->after('supplier_note');
        });

        Schema::table('warranty_claim_histories', function (Blueprint $table) {
            // Sejajar is_followup: entri riwayat yang bukan transisi tahap.
            $table->boolean('is_notify')->default(false)->after('is_followup');
        });

        // --- 4. Pengiriman klaim ke supplier (satu vendor, banyak unit) ---
        Schema::create('warranty_supplier_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();  // KS-{YYMM}-{urut}

            // restrictOnDelete: pengiriman itu bukti tagihan ke vendor —
            // vendornya tidak boleh lenyap dan bikin surat tanda terima yatim.
            $table->foreignId('vendor_id')->constrained('warranty_vendors')->restrictOnDelete();

            $table->string('status', 20)->default('draft');   // draft/dikirim/dicek/selesai
            $table->text('note')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // SLA sendiri — kembaran pola di warranty_claims. Klaim ke supplier
            // yang mangkrak itu duit kita yang nyangkut, harus kelihatan merah.
            $table->timestamp('last_followed_up_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warranty_supplier_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('warranty_supplier_shipments')->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('warranty_claims')->restrictOnDelete();

            // Hasil DIPUTUSKAN PER UNIT, bukan per pengiriman: satu kiriman bisa
            // saja sebagian diganti barang, sebagian diganti uang, sebagian ditolak.
            $table->string('outcome', 20)->nullable();        // ganti_produk/ganti_uang/ditolak
            $table->unsignedBigInteger('refund_amount')->nullable(); // rupiah, hanya utk ganti_uang
            $table->text('outcome_note')->nullable();

            // menunggu → (ganti_produk) dikirim_gudang → selesai
            //          → (ganti_uang / ditolak) langsung selesai
            $table->string('status', 20)->default('menunggu');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // Satu klaim tidak boleh dobel di pengiriman yang sama. Boleh masuk
            // pengiriman LAIN (mis. ditolak vendor A, dicoba lagi ke vendor B) —
            // itu sebabnya unique-nya berpasangan, bukan claim_id sendirian.
            $table->unique(['shipment_id', 'claim_id']);
        });

        // --- 5. Klaim yang sudah ada: semuanya alur lama ---
        DB::table('warranty_claims')->update(['flow' => 'kirim_dulu']);
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_supplier_shipment_items');
        Schema::dropIfExists('warranty_supplier_shipments');

        Schema::table('warranty_claim_histories', function (Blueprint $table) {
            $table->dropColumn('is_notify');
        });

        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropColumn([
                'flow', 'supplier_queued_at', 'supplier_skipped_at',
                'supplier_note', 'last_notified_at',
            ]);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warranty_vendor_id');
        });

        Schema::table('warranty_vendors', function (Blueprint $table) {
            $table->dropColumn('requires_send_first');
        });
    }
};
