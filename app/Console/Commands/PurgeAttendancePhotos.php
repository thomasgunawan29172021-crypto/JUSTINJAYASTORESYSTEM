<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeAttendancePhotos extends Command
{
    protected $signature = 'attendance:purge-photos';

    protected $description = 'Hapus foto selfie absen > 45 hari (kebijakan retensi Thomas). Record absennya TETAP disimpan.';

    public function handle(): int
    {
        $olds = Attendance::whereDate('work_date', '<', today()->subDays(45))
            ->where(fn ($q) => $q->whereNotNull('clock_in_photo')->orWhereNotNull('clock_out_photo'))
            ->get();

        foreach ($olds as $a) {
            foreach (['clock_in_photo', 'clock_out_photo'] as $col) {
                if ($a->{$col}) {
                    Storage::disk(config('filesystems.default'))->delete($a->{$col});
                }
            }
            $a->update(['clock_in_photo' => null, 'clock_out_photo' => null]);
        }

        $this->info("Foto dibersihkan dari {$olds->count()} record.");

        return self::SUCCESS;
    }
}