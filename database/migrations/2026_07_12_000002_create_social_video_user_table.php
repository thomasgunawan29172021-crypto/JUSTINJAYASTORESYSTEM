<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembuat video. is_pic = true → dapat kredit KPI (target, views, leaderboard).
        // Video solo: 1 baris is_pic=true. Video colab: 1 PIC + N anggota (is_pic=false, info saja).
        Schema::create('social_video_user', function (Blueprint $table) {
            $table->foreignId('social_video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pic')->default(false);
            $table->primary(['social_video_id', 'user_id']);
            $table->index(['user_id', 'is_pic']);
        });

        Schema::table('social_videos', function (Blueprint $table) {
            $table->boolean('is_collab')->default(false)->after('theme');
        });

        // Migrasi data lama: pembuat tunggal → PIC. Termasuk video yang sudah di-soft-delete,
        // supaya kalau nanti di-restore, PIC-nya tidak hilang.
        foreach (DB::table('social_videos')->whereNotNull('user_id')->get(['id', 'user_id']) as $v) {
            DB::table('social_video_user')->insertOrIgnore([
                'social_video_id' => $v->id, 'user_id' => $v->user_id, 'is_pic' => true,
            ]);
        }

        // Kolom lama dipensiunkan (nullable, tidak dipakai query lagi). Drop menyusul kalau sudah yakin.
        Schema::table('social_videos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_videos', function (Blueprint $table) {
            $table->dropColumn('is_collab');
        });
        Schema::dropIfExists('social_video_user');
    }
};
