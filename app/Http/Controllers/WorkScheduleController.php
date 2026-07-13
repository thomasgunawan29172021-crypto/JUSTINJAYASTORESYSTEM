<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function index()
    {
        return view('attendance.schedules', [
            'users' => User::with(['workSchedule', 'branch'])
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function upsert(Request $request, User $user)
    {
        $data = $request->validate([
            'clock_in_time'  => ['required', 'date_format:H:i'],
            'clock_out_time' => ['required', 'date_format:H:i'],
            'off_day'        => ['required', 'integer', 'min:0', 'max:6'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $user->workSchedule()->updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('ok', "Jadwal {$user->name} disimpan.");
    }
}