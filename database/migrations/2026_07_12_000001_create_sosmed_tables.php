<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Video sosmed per pegawai. Input oleh PIC Sosmed (role: sosmed) atau CEO.
        Schema::create('social_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');            // pembuat video
            $table->foreignId('added_by')->nullable()->constrained('users'); // yang menginput (audit)
            $table->string('platform', 20);                                  // tiktok|facebook|youtube|instagram
            $table->string('title', 200);
            $table->string('url', 500)->unique();                            // 1 URL = 1 video, cegah dobel
            $table->string('theme', 100)->nullable();                        // format/tema konten
            $table->date('published_at');
            $table->timestamp('frozen_at')->nullable();                      // null = masih dilacak (M2)
            $table->timestamps();
            $table->softDeletes();                                           // konsisten pola trash

            $table->index(['user_id', 'published_at']);
            $table->index(['platform', 'published_at']);
        });

        // Snapshot metrik berkala — 1 baris per pencatatan. Angka terkini = snapshot terakhir.
        Schema::create('video_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamp('recorded_at');

            $table->index(['social_video_id', 'recorded_at']);
        });

        // Target setoran video — riwayat tersimpan, laporan lama dinilai pakai target saat itu.
        Schema::create('social_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('video_count');
            $table->string('period', 10);                                    // daily|weekly|monthly
            $table->date('effective_from');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_targets');
        Schema::dropIfExists('video_metric_snapshots');
        Schema::dropIfExists('social_videos');
    }
};
