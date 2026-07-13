<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master platform — CRUD oleh CEO (P2). domains buat validasi link (null = tanpa validasi domain).
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('domains', 300)->nullable(); // dipisah koma: "youtube.com,youtu.be"
            $table->timestamps();
        });

        // Posting = 1 video tayang di 1 platform. Unit pelacakan metrik yang baru.
        Schema::create('social_video_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained(); // hapus platform dijaga di level app (P2)
            $table->string('url', 500)->unique();
            $table->timestamps();

            $table->unique(['social_video_id', 'platform_id']); // 1 video max 1 posting per platform
        });

        // Snapshot pindah induk: video → posting
        Schema::table('video_metric_snapshots', function (Blueprint $table) {
            $table->foreignId('social_video_platform_id')->nullable()
                ->after('social_video_id')->constrained('social_video_platform')->cascadeOnDelete();
        });

        /* ---------- Migrasi data lama ---------- */
        $seed = [
            'tiktok'    => ['TikTok',    'tiktok.com'],
            'facebook'  => ['Facebook',  'facebook.com,fb.watch'],
            'youtube'   => ['YouTube',   'youtube.com,youtu.be'],
            'instagram' => ['Instagram', 'instagram.com'],
        ];
        $ids = [];
        foreach ($seed as $key => [$name, $domains]) {
            $ids[$key] = DB::table('platforms')->insertGetId([
                'name' => $name, 'domains' => $domains, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('social_videos')->whereNotNull('url')->get(['id', 'platform', 'url']) as $v) {
            $platformId = $ids[$v->platform] ?? null;
            if (! $platformId) { // platform tak dikenal → buat baru apa adanya
                $platformId = DB::table('platforms')->insertGetId([
                    'name' => ucfirst($v->platform), 'domains' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $ids[$v->platform] = $platformId;
            }

            $postingId = DB::table('social_video_platform')->insertGetId([
                'social_video_id' => $v->id, 'platform_id' => $platformId, 'url' => $v->url,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('video_metric_snapshots')
                ->where('social_video_id', $v->id)
                ->update(['social_video_platform_id' => $postingId]);
        }

        // Kolom lama dipensiunkan (pola sama dengan user_id): nullable, tidak dipakai query lagi.
        Schema::table('social_videos', function (Blueprint $table) {
            $table->string('platform', 20)->nullable()->change();
            $table->string('url', 500)->nullable()->change();
        });
        Schema::table('video_metric_snapshots', function (Blueprint $table) {
            $table->foreignId('social_video_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_metric_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_video_platform_id');
        });
        Schema::dropIfExists('social_video_platform');
        Schema::dropIfExists('platforms');
    }
};