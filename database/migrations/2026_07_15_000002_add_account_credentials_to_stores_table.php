<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Pegangan CEO buat login ke akun toko marketplace — TIDAK mempengaruhi sistem/task engine.
            $table->string('account_email', 150)->nullable()->after('is_mall');
            $table->string('account_phone', 20)->nullable()->after('account_email');
            $table->text('account_password')->nullable()->after('account_phone'); // TEXT wajib — hasil enkripsi lebih panjang dari plaintext
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['account_email', 'account_phone', 'account_password']);
        });
    }
};
