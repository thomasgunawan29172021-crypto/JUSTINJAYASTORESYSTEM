<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timer SLA gak boleh baca updated_at: kolom itu berubah tiap save apa pun —
     * benerin typo nomor HP aja bikin klaim macet 13 hari kelihatan "baru aktif".
     * last_activity_at HANYA di-stamp oleh open()/advance()/followUp().
     */
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('last_followed_up_at');
        });

        // Backfill dari updated_at — buat klaim tes yang udah ada. Di production
        // tabelnya masih kosong pas ini jalan, jadi no-op.
        DB::table('warranty_claims')->update(['last_activity_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
