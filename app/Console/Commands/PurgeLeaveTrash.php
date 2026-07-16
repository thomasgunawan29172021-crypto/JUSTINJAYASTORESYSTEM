<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use Illuminate\Console\Command;

class PurgeLeaveTrash extends Command
{
    protected $signature = 'leaves:purge-trash';

    protected $description = 'Hapus permanen pengajuan cuti/izin di sampah yang sudah lebih dari 60 hari';

    public function handle(): int
    {
        $stale = LeaveRequest::onlyTrashed()->where('deleted_at', '<=', now()->subDays(60));
        $count = $stale->count();
        $stale->forceDelete();

        $this->info("{$count} pengajuan cuti di sampah (>60 hari) dihapus permanen.");

        return self::SUCCESS;
    }
}
