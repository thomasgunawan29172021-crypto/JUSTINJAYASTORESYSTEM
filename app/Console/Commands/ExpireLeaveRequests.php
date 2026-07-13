<?php

namespace App\Console\Commands;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use Illuminate\Console\Command;

class ExpireLeaveRequests extends Command
{
    protected $signature = 'leaves:expire';

    protected $description = 'Kedaluwarsakan pengajuan pending > 7 hari sejak diajukan (keputusan Thomas: batal otomatis, ajukan ulang)';

    public function handle(): int
    {
        $expired = LeaveRequest::where('status', LeaveStatus::Pending->value)
            ->where('created_at', '<=', now()->subDays(7))
            ->get();

        foreach ($expired as $l) {
            $l->update([
                'status'        => LeaveStatus::Expired,
                'decision_note' => 'Kedaluwarsa otomatis — tidak diputuskan dalam 7 hari sejak diajukan.',
            ]);
        }

        $this->info("Kedaluwarsa: {$expired->count()} pengajuan.");

        return self::SUCCESS;
    }
}