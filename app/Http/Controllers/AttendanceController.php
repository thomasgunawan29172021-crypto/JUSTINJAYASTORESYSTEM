<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $schedule = $user->workSchedule;

        return view('attendance.index', [
            'schedule'      => $schedule,
            'todaySchedule' => $schedule?->dayFor(now()->dayOfWeek),
            'branch'        => $user->branch,
            'today'    => Attendance::where('user_id', $user->id)
                ->whereDate('work_date', today())->first(),
            'history'  => Attendance::where('user_id', $user->id)
                ->orderByDesc('work_date')->limit(14)->get(),
        ]);
    }

    public function clockIn(Request $request)
    {
        $user = $request->user();
        [$lat, $lng, $photo] = $this->validateAbsen($request);

        $schedule = $user->workSchedule;
        if (! $schedule) {
            return back()->withErrors(['absen' => 'Jadwal kerja Anda belum diatur. Hubungi CEO.']);
        }

        [$distance, $err] = $this->checkGeofence($user, $lat, $lng);
        if ($err) {
            return back()->withErrors(['absen' => $err]);
        }

        if (Attendance::where('user_id', $user->id)->whereDate('work_date', today())->exists()) {
            return back()->withErrors(['absen' => 'Anda sudah absen masuk hari ini.']);
        }

        // Jadwal per HARI (bukan lagi satu off_day tunggal) — cek jam hari ini spesifik.
        $todayDay = $schedule->dayFor(now()->dayOfWeek);
        $isOffDay = ! $todayDay || $todayDay->clock_in_time === null;

        $late = 0;
        if (! $isOffDay) {
            $scheduled = today()->setTimeFromTimeString($todayDay->clock_in_time);
            // Menit MENTAH — toleransi 5 menit dinilai di Attendance::isLate(), bukan dibakar ke data
            $late = now()->greaterThan($scheduled) ? (int) $scheduled->diffInMinutes(now()) : 0;
        }

        Attendance::create([
            'user_id'             => $user->id,
            'work_date'           => today(),
            'clock_in_at'         => now(),
            'clock_in_lat'        => $lat,
            'clock_in_lng'        => $lng,
            'clock_in_distance_m' => $distance,
            'clock_in_photo'      => $this->storePhoto($photo, $user->id, 'in'),
            'late_minutes'        => $late,
            'is_off_day'          => $isOffDay,
        ]);

        return back()->with('ok', 'Absen masuk tercatat '.now()->format('H:i').'.');
    }

    public function clockOut(Request $request)
    {
        $user = $request->user();
        [$lat, $lng, $photo] = $this->validateAbsen($request);

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', today())->first();

        if (! $attendance) {
            return back()->withErrors(['absen' => 'Belum ada absen masuk hari ini.']);
        }
        if ($attendance->clock_out_at !== null) {
            return back()->withErrors(['absen' => 'Anda sudah absen pulang hari ini.']);
        }

        // Clock-out juga wajib di dalam geofence — cegah "cabut sore, absen pulang dari rumah"
        [$distance, $err] = $this->checkGeofence($user, $lat, $lng);
        if ($err) {
            return back()->withErrors(['absen' => $err]);
        }

        $attendance->update([
            'clock_out_at'         => now(),
            'clock_out_lat'        => $lat,
            'clock_out_lng'        => $lng,
            'clock_out_distance_m' => $distance,
            'clock_out_photo'      => $this->storePhoto($photo, $user->id, 'out'),
        ]);

        return back()->with('ok', 'Absen pulang tercatat '.now()->format('H:i').'.');
    }

    /* ------------------------- Helper ------------------------- */

    /** Karyawan mengirim selfie ulang atas permintaan CEO. Tanpa geofence — momen aslinya sudah lewat. */
    public function submitRetake(Request $request, Attendance $attendance)
    {
        abort_unless($attendance->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'type'  => ['required', 'in:in,out'],
            'photo' => ['required', 'string', 'starts_with:data:image/jpeg;base64,'],
        ]);

        $flag  = $data['type'] === 'in' ? 'retake_in_requested' : 'retake_out_requested';
        $field = $data['type'] === 'in' ? 'clock_in_photo' : 'clock_out_photo';
        $other = $data['type'] === 'in' ? 'retake_out_requested' : 'retake_in_requested';

        if (! $attendance->{$flag}) {
            return back()->withErrors(['retake' => 'Tidak ada permintaan foto ulang untuk absen ini.']);
        }

        $path = $this->storePhoto($data['photo'], $attendance->user_id, $data['type'].'-retake');

        // Alasan hanya dibuang kalau TIDAK ada permintaan lain yang masih menunggu.
        // Dicek dari flag SATUNYA — flag ini sendiri baru saja kita matikan.
        $stillPending = (bool) $attendance->{$other};

        $attendance->update([
            $field          => $path,
            $flag           => false,
            'retake_reason' => $stillPending ? $attendance->retake_reason : null,
        ]);

        return back()->with('ok', 'Foto ulang terkirim. Terima kasih.');
    }

    protected function validateAbsen(Request $request): array
    {
        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo'     => ['required', 'string', 'starts_with:data:image/jpeg;base64,'],
        ]);

        return [(float) $data['latitude'], (float) $data['longitude'], $data['photo']];
    }

    /** @return array{0: ?int, 1: ?string} [jarak_meter, pesan_error] */
    protected function checkGeofence($user, float $lat, float $lng): array
    {
        $branch = $user->branch;

        if (! $branch) {
            return [null, 'Akun Anda belum terhubung ke cabang. Hubungi CEO.'];
        }
        if ($branch->latitude === null || $branch->longitude === null) {
            return [null, "Koordinat {$branch->name} belum diatur. Hubungi CEO."];
        }

        $distance = $branch->distanceToMeters($lat, $lng);

        if ($distance > $branch->geofence_radius_m) {
            return [null, "Lokasi Anda {$distance} m dari {$branch->name} (maks {$branch->geofence_radius_m} m). Absen harus dilakukan di toko."];
        }

        return [$distance, null];
    }

    protected function storePhoto(string $dataUrl, int $userId, string $suffix): string
    {
        $binary = base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true);

        abort_if($binary === false || strlen($binary) > 2 * 1024 * 1024, 422, 'Foto tidak valid.');

        $path = 'attendance/'.$userId.'/'.today()->toDateString().'-'.$suffix.'-'.Str::random(8).'.jpg';
        Storage::disk(config('filesystems.default'))->put($path, $binary);

        return $path;
    }
}