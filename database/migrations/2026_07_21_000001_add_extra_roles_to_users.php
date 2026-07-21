<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-role Jalan B: `role` tetap jabatan utama (label, payroll, identitas),
     * `extra_roles` (json) cuma NAMBAH hak akses. Akses = union keduanya.
     * CEO dikecualikan — gak boleh muncul di extra_roles (dipagari di validasi
     * UserManagementController, bukan di DB).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('extra_roles')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('extra_roles');
        });
    }
};
