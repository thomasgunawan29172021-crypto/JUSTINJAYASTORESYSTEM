<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function index()
    {
        return view('attendance.schedules', [
            'users' => User::with(['workSchedule.days', 'branch'])
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Simpan effective_from + 7 baris jadwal harian sekaligus. */
    public function upsert(Request $request, User $user)
    {
        $request->validate(['effective_from' => ['nullable', 'date']]);

        $schedule = $user->workSchedule()->updateOrCreate(
            ['user_id' => $user->id],
            ['effective_from' => $request->input('effective_from')]
        );

        foreach (range(0, 6) as $dow) {
            $input = $request->input("days.{$dow}", []);
            $isOff = ! empty($input['is_off']);

            if (! $isOff) {
                $request->validate([
                    "days.{$dow}.clock_in_time"  => ['required', 'date_format:H:i'],
                    "days.{$dow}.clock_out_time" => ['required', 'date_format:H:i'],
                ]);
            }

            $schedule->days()->updateOrCreate(
                ['day_of_week' => $dow],
                [
                    'clock_in_time'  => $isOff ? null : $input['clock_in_time'],
                    'clock_out_time' => $isOff ? null : $input['clock_out_time'],
                ]
            );
        }

        return back()->with('ok', "Jadwal {$user->name} disimpan.");
    }
}