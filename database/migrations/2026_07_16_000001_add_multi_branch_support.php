<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'user_id']);
            $table->index(['user_id', 'branch_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Nullable supaya riwayat tetap aman bila suatu cabang dihapus.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'work_date']);
        });

        // Semua akun lama otomatis mendapat cabang utamanya sebagai lokasi absensi.
        DB::table('users')
            ->whereNotNull('branch_id')
            ->select(['id', 'branch_id'])
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                $now = now();
                $rows = $users->map(fn ($user) => [
                    'branch_id' => $user->branch_id,
                    'user_id'    => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('branch_user')->insertOrIgnore($rows);
            });

        // Riwayat absensi lama ditandai menggunakan cabang utama user saat migrasi.
        DB::table('attendances')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(500, function ($attendances) {
                $branchByUser = DB::table('users')
                    ->whereIn('id', $attendances->pluck('user_id'))
                    ->pluck('branch_id', 'id');

                foreach ($attendances as $attendance) {
                    $branchId = $branchByUser[$attendance->user_id] ?? null;

                    if ($branchId !== null) {
                        DB::table('attendances')
                            ->where('id', $attendance->id)
                            ->update(['branch_id' => $branchId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'work_date']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branch_user');
    }
};
