<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttendanceCorrectionController extends Controller
{
    /** Snapshot kolom yang diaudit. */
    protected const TRACKED = ['clock_in_at', 'clock_out_at', 'late_minutes', 'auto_closed', 'is_off_day'];

    public function edit(Attendance $attendance)
    {
        $attendance->load(['user.workSchedule', 'corrections.corrector']);

        return view('attendance.correction-form', [
            'mode'       => 'edit',
            'attendance' => $attendance,
            'user'       => $attendance->user,
            'date'       => $attendance->work_date,
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $this->validateTimes($request);

        $before = $attendance->only(self::TRACKED);

        $attendance->clock_in_at  = $attendance->work_date->copy()->setTimeFromTimeString($data['clock_in']);
        $attendance->clock_out_at = $data['clock_out']
            ? $attendance->work_date->copy()->setTimeFromTimeString($data['clock_out'])
            : null;
        $attendance->auto_closed  = false; // koreksi manual = sudah direview, tanda auto-close dicabut
        $attendance->late_minutes = $this->recomputeLate($attendance);
        $attendance->save();

        $attendance->corrections()->create([
            'corrected_by' => $request->user()->id,
            'before'       => $before,
            'after'        => $attendance->only(self::TRACKED),
            'reason'       => $data['reason'],
            'created_at'   => now(),
        ]);

        return redirect()
            ->route('attendance.recap.show', ['user' => $attendance->user_id, 'month' => $attendance->work_date->format('Y-m')])
            ->with('ok', 'Absen dikoreksi — tercatat di audit trail.');
    }

    public function create(Request $request, User $user)
    {
        return view('attendance.correction-form', [
            'mode'       => 'create',
            'attendance' => null,
            'user'       => $user,
            'date'       => Carbon::parse($request->query('date', today()->toDateString())),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $data = $this->validateTimes($request, withDate: true);

        $workDate = Carbon::parse($data['work_date']);

        if (Attendance::where('user_id', $user->id)->whereDate('work_date', $workDate)->exists()) {
            return back()->withInput()->withErrors([
                'work_date' => 'Sudah ada absen di tanggal ini — gunakan tombol Koreksi, bukan input manual.',
            ]);
        }

        $schedule = $user->workSchedule;
        $isOff    = $schedule && $schedule->isOffDay($workDate->dayOfWeek);

        $attendance = new Attendance([
            'user_id'      => $user->id,
            'work_date'    => $workDate,
            'clock_in_at'  => $workDate->copy()->setTimeFromTimeString($data['clock_in']),
            'clock_out_at' => $data['clock_out'] ? $workDate->copy()->setTimeFromTimeString($data['clock_out']) : null,
            'is_off_day'   => $isOff,
            'auto_closed'  => false,
            // Foto & lokasi memang null: ini input manual CEO, bukan absen GPS.
        ]);
        $attendance->late_minutes = $this->recomputeLate($attendance);
        $attendance->save();

        $attendance->corrections()->create([
            'corrected_by' => $request->user()->id,
            'before'       => null, // penanda: dibuat manual, bukan koreksi data lama
            'after'        => $attendance->only(self::TRACKED),
            'reason'       => $data['reason'],
            'created_at'   => now(),
        ]);

        return redirect()
            ->route('attendance.recap.show', ['user' => $user->id, 'month' => $workDate->format('Y-m')])
            ->with('ok', 'Absen manual dibuat — tercatat di audit trail.');
    }

    /* ------------------------- Helper ------------------------- */

    protected function validateTimes(Request $request, bool $withDate = false): array
    {
        $rules = [
            'clock_in'  => ['required', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'reason'    => ['required', 'string', 'max:500'],
        ];

        if ($withDate) {
            $rules['work_date'] = ['required', 'date', 'before_or_equal:today'];
        }

        return $request->validate($rules, [
            'clock_out.after' => 'Jam pulang harus setelah jam masuk.',
            'reason.required' => 'Alasan koreksi wajib diisi — ini masuk audit trail.',
        ]);
    }

    protected function recomputeLate(Attendance $attendance): int
    {
        $schedule = $attendance->user()->first()?->workSchedule;
        $scheduleDay = $schedule?->dayFor($attendance->work_date->dayOfWeek);

        if (! $scheduleDay || $scheduleDay->clock_in_time === null || $attendance->is_off_day) {
            return 0;
        }

        $scheduled = $attendance->work_date->copy()->setTimeFromTimeString($scheduleDay->clock_in_time);

        return $attendance->clock_in_at->greaterThan($scheduled)
            ? (int) $scheduled->diffInMinutes($attendance->clock_in_at)
            : 0;
    }

    /** CEO menghapus foto & meminta selfie ulang. Tercatat di audit trail. */
    public function requestRetake(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'type'   => ['required', 'in:in,out'],
            'reason' => ['required', 'string', 'max:300'],
        ], ['reason.required' => 'Tulis alasannya — karyawan perlu tahu kenapa fotonya diminta ulang.']);

        $field = $data['type'] === 'in' ? 'clock_in_photo' : 'clock_out_photo';
        $flag  = $data['type'] === 'in' ? 'retake_in_requested' : 'retake_out_requested';

        if (! $attendance->{$field}) {
            return back()->withErrors(['retake' => 'Tidak ada foto untuk diminta ulang.']);
        }

        $oldPath = $attendance->{$field};

        DB::transaction(function () use ($attendance, $request, $data, $field, $flag, $oldPath) {
            $attendance->corrections()->create([
                'corrected_by' => $request->user()->id,
                'before'       => [$field => $oldPath],
                'after'        => [$field => null, $flag => true],
                'reason'       => '[Foto ulang] '.$data['reason'],
                'created_at'   => now(),
            ]);

            $attendance->update([$field => null, $flag => true, 'retake_reason' => $data['reason']]);
        });

        // Hapus file SETELAH transaksi sukses — kalau transaksi gagal, foto tidak ikut hilang
        Storage::disk(config('filesystems.default'))->delete($oldPath);

        return back()->with('ok', 'Foto dihapus — karyawan diminta selfie ulang.');
    }
}
