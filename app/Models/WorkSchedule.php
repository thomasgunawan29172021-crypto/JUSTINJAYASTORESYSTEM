<?php

namespace App\Models;

use App\Models\WorkScheduleDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    public const DAYS = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    protected $fillable = ['user_id', 'effective_from'];

    protected $casts = ['effective_from' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class);
    }

    public function dayFor(int $dayOfWeek): ?WorkScheduleDay
    {
        return $this->days->firstWhere('day_of_week', $dayOfWeek);
    }

    /** Libur = tidak ada baris untuk hari itu, ATAU jamnya kosong. */
    public function isOffDay(int $dayOfWeek): bool
    {
        $day = $this->dayFor($dayOfWeek);

        return ! $day || $day->clock_in_time === null;
    }

    /** Ringkasan kebaca: "Sen,Sel,Rab,Kam,Jum 08:00–17:00 · Sab 09:00–14:00 · Min Libur". */
    public function summary(): string
    {
        $order  = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 0 => 'Min'];
        $byDay  = $this->days->keyBy('day_of_week');
        $groups = [];

        foreach ($order as $dow => $label) {
            $d   = $byDay->get($dow);
            $key = ($d && $d->clock_in_time)
                ? substr($d->clock_in_time, 0, 5).'–'.substr($d->clock_out_time, 0, 5)
                : 'Libur';
            $groups[$key][] = $label;
        }

        return collect($groups)->map(fn ($days, $time) => implode(',', $days).' '.$time)->implode(' · ');
    }
}