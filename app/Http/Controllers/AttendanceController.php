<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $schedule = $user->workSchedule;
        $branches = $this->attendanceBranches($user);

        $today = Attendance::with('branch')
            ->where('user_id', $user->id)
            ->whereDate('work_date', today())
            ->first();

        return view('attendance.index', [
            'schedule'      => $schedule,
            'todaySchedule' => $schedule?->dayFor(now()->dayOfWeek),
            // Dipertahankan untuk kompatibilitas view lama.
            'branch'        => $today?->branch ?? $user->branch ?? $branches->first(),
            // View baru boleh menampilkan seluruh cabang yang diizinkan.
            'branches'      => $branches,
            'today'         => $today,
            'history'       => Attendance::with('branch')
                ->where('user_id', $user->id)
                ->orderByDesc('work_date')
                ->limit(14)
                ->get(),
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

        [$branch, $distance, $err] = $this->checkAllowedBranchesGeofence($user, $lat, $lng);
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
            'branch_id'           => $branch->id,
            'work_date'           => today(),
            'clock_in_at'         => now(),
            'clock_in_lat'        => $lat,
            'clock_in_lng'        => $lng,
            'clock_in_distance_m' => $distance,
            'clock_in_photo'      => $this->storePhoto($photo, $user->id, 'in'),
            'late_minutes'        => $late,
            'is_off_day'          => $isOffDay,
        ]);

        return back()->with('ok', "Absen masuk di {$branch->name} tercatat ".now()->format('H:i').'.');
    }

    public function clockOut(Request $request)
    {
        $user = $request->user();
        [$lat, $lng, $photo] = $this->validateAbsen($request);

        $attendance = Attendance::with('branch')
            ->where('user_id', $user->id)
            ->whereDate('work_date', today())
            ->first();

        if (! $attendance) {
            return back()->withErrors(['absen' => 'Belum ada absen masuk hari ini.']);
        }
        if ($attendance->clock_out_at !== null) {
            return back()->withErrors(['absen' => 'Anda sudah absen pulang hari ini.']);
        }

        // Clock-out harus di cabang yang sama dengan clock-in agar rekap cabang tetap jelas.
        $branch = $attendance->branch;

        if ($branch) {
            [$distance, $err] = $this->checkBranchGeofence($branch, $lat, $lng);
        } else {
            // Pengaman untuk record lama yang belum memiliki branch_id.
            [$branch, $distance, $err] = $this->checkAllowedBranchesGeofence($user, $lat, $lng);
        }

        if ($err) {
            return back()->withErrors(['absen' => $err]);
        }

        $attendance->update([
            'branch_id'            => $attendance->branch_id ?? $branch->id,
            'clock_out_at'         => now(),
            'clock_out_lat'        => $lat,
            'clock_out_lng'        => $lng,
            'clock_out_distance_m' => $distance,
            'clock_out_photo'      => $this->storePhoto($photo, $user->id, 'out'),
        ]);

        return back()->with('ok', "Absen pulang dari {$branch->name} tercatat ".now()->format('H:i').'.');
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

    /**
     * Ambil cabang absensi dari pivot. Cabang utama tetap ikut sebagai fallback
     * agar akun lama tetap bisa absen walau migrasi/sync belum sempat dijalankan.
     */
    protected function attendanceBranches(User $user): Collection
    {
        $user->loadMissing(['branch', 'branches']);

        $branches = $user->branches->values();

        if ($user->branch && ! $branches->contains('id', $user->branch->id)) {
            $branches->push($user->branch);
        }

        return $branches->unique('id')->sortBy('name')->values();
    }

    /**
     * Cari cabang berizin yang paling dekat dan masih berada di dalam radius.
     *
     * @return array{0: ?Branch, 1: ?int, 2: ?string}
     */
    protected function checkAllowedBranchesGeofence(User $user, float $lat, float $lng): array
    {
        $branches = $this->attendanceBranches($user);

        if ($branches->isEmpty()) {
            return [null, null, 'Akun Anda belum terhubung ke cabang. Hubungi CEO.'];
        }

        $measured = $branches
            ->filter(fn (Branch $branch) =>
                $branch->latitude !== null
                && $branch->longitude !== null
                && (int) $branch->geofence_radius_m > 0
            )
            ->map(function (Branch $branch) use ($lat, $lng) {
                return [
                    'branch'   => $branch,
                    'distance' => $branch->distanceToMeters($lat, $lng),
                    'radius'   => (int) $branch->geofence_radius_m,
                ];
            })
            ->filter(fn (array $item) => $item['distance'] !== null)
            ->sortBy('distance')
            ->values();

        if ($measured->isEmpty()) {
            return [
                null,
                null,
                'Koordinat atau radius cabang akun Anda belum diatur. Hubungi CEO.',
            ];
        }

        $inside = $measured->first(fn (array $item) => $item['distance'] <= $item['radius']);

        if ($inside) {
            return [$inside['branch'], $inside['distance'], null];
        }

        $nearest = $measured->first();
        $allowedNames = $branches->pluck('name')->implode(', ');

        return [
            null,
            null,
            "Lokasi Anda {$nearest['distance']} m dari {$nearest['branch']->name} "
                ."(maks {$nearest['radius']} m). Cabang absensi akun: {$allowedNames}.",
        ];
    }

    /** @return array{0: ?int, 1: ?string} */
    protected function checkBranchGeofence(Branch $branch, float $lat, float $lng): array
    {
        if ($branch->latitude === null || $branch->longitude === null) {
            return [null, "Koordinat {$branch->name} belum diatur. Hubungi CEO."];
        }

        $radius = (int) $branch->geofence_radius_m;
        if ($radius <= 0) {
            return [null, "Radius absensi {$branch->name} belum diatur. Hubungi CEO."];
        }

        $distance = $branch->distanceToMeters($lat, $lng);

        if ($distance === null || $distance > $radius) {
            $distanceText = $distance === null ? 'tidak dapat dihitung' : "{$distance} m";

            return [
                null,
                "Absen pulang harus dilakukan di {$branch->name}. Jarak Anda {$distanceText} (maks {$radius} m).",
            ];
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
