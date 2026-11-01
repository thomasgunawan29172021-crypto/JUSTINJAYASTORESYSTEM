<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL melakukan auto-commit untuk ALTER TABLE. Pengecekan kolom ini
        // membuat migration aman diulang jika proses sebelumnya terhenti di tengah.
        if (! Schema::hasColumn('users', 'promotor_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('promotor_type', 20)->nullable()->after('base_salary')->index();
            });
        }

        // Unique index lama ikut dipakai MySQL sebagai index foreign key branch_id.
        // Sediakan index khusus lebih dulu agar unique index tersebut boleh dilepas.
        if (! Schema::hasIndex('incentive_tiers', 'incentive_tiers_branch_id_index')) {
            Schema::table('incentive_tiers', function (Blueprint $table) {
                $table->index('branch_id');
            });
        }
        if (Schema::hasIndex('incentive_tiers', 'incentive_tier_unique')) {
            Schema::table('incentive_tiers', function (Blueprint $table) {
                $table->dropUnique('incentive_tier_unique');
            });
        }

        if (! Schema::hasColumn('incentive_tiers', 'effective_from')) {
            Schema::table('incentive_tiers', function (Blueprint $table) {
                // Data lama tetap sah sebagai konfigurasi awal. Perubahan baru memakai
                // snapshot bertanggal 1 bulan berikutnya.
                $table->date('effective_from')->default('2000-01-01')->after('type');
            });
        }
        if (! Schema::hasIndex('incentive_tiers', 'incentive_tier_version_unique')) {
            Schema::table('incentive_tiers', function (Blueprint $table) {
                $table->unique(
                    ['branch_id', 'currency', 'type', 'effective_from', 'min_target'],
                    'incentive_tier_version_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('incentive_tiers', function (Blueprint $table) {
            $table->dropUnique('incentive_tier_version_unique');
            $table->dropColumn('effective_from');
            $table->unique(['branch_id', 'currency', 'type', 'min_target'], 'incentive_tier_unique');
        });

        Schema::table('incentive_tiers', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
        });

        if (Schema::hasColumn('users', 'promotor_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['promotor_type']);
                $table->dropColumn('promotor_type');
            });
        }
    }
};
