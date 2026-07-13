<?php

namespace App\Console\Commands;

use App\Models\SocialVideo;
use Illuminate\Console\Command;

class FreezeStaleSosmedVideos extends Command
{
    protected $signature   = 'sosmed:freeze-stale';
    protected $description = 'Bekukan paksa video sosmed yang lewat 30 hari tanpa update final.';

    public function handle(): int
    {
        $count = SocialVideo::active()
            ->where('published_at', '<=', now()->subDays(SocialVideo::FORCE_DAYS)->toDateString())
            ->update(['frozen_at' => now()]);

        $this->info("{$count} video dibekukan paksa.");

        return self::SUCCESS;
    }
}