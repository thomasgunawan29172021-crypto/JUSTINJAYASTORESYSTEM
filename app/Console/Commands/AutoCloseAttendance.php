<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class AutoCloseAttendance extends Command
{
    protected $signature = 'attendance:auto-close';

    protected $description = 'Tutup otomatis absen yang belum clock-out, set jam 23:49, tandai untuk review CEO';

    public function handle(): int
    {
        // <= today (bukan == today): kalau scheduler sempat mati beberapa hari,
        // record gantung hari-hari sebelumnya ikut dibereskan (self-healing)
        $hanging = Attendance::whereNull('clock_out_at')
            ->whereDate('work_date', '<=', today())
            ->get();

        foreach ($hanging as $a) {
            $a->update([
                'clock_out_at' => $a->work_date->copy()->setTime(23, 49),
                'auto_closed'  => true,
            ]);
        }

        $this->info("Ditutup otomatis: {$hanging->count()} absen.");

        return self::SUCCESS;
    }
}