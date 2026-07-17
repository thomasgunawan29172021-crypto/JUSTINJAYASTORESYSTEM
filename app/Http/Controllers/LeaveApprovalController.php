<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveApprovalController extends Controller
{
    public function index()
    {
        return view('leaves.manage', [
            'pendings'   => LeaveRequest::with('user.branch')
                ->where('status', LeaveStatus::Pending->value)
                ->orderBy('date_from')->get(),
            'recents'    => LeaveRequest::with(['user', 'decider'])
                ->where('status', '!=', LeaveStatus::Pending->value)
                ->orderByDesc('decided_at')->limit(15)->get(),
            'trashCount' => LeaveRequest::onlyTrashed()->count(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate([
            'decision'      => ['required', Rule::in(['approve', 'reject'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
            'is_paid'       => ['nullable', 'boolean'],
            // CEO boleh mengubah jam usulan staf saat menyetujui — pola yang sama
            // dengan override is_paid di bawah.
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => 'Jam pulang harus setelah jam masuk.',
        ]);

        if ($leave->status !== LeaveStatus::Pending) {
            return back()->withErrors(['leave' => 'Pengajuan ini sudah diputuskan.']);
        }

        // Tidak boleh menyetujui pengajuan milik sendiri (kepala toko/CEO yang cuti → orang lain yang putuskan)
        if ($leave->user_id === $request->user()->id) {
            return back()->withErrors(['leave' => 'Anda tidak bisa memutuskan pengajuan milik sendiri.']);
        }

        $approved = $data['decision'] === 'approve';

        // Status bayar: ikut default per jenis. KHUSUS izin pribadi,
        // HANYA CEO yang boleh override jadi "tidak dipotong" (kebijakan Thomas).
        $isPaid = null;
        if ($approved) {
            $isPaid = $leave->type->defaultPaid();
            if ($leave->type === LeaveType::Izin && $request->user()->role->isCeo()) {
                $isPaid = $request->boolean('is_paid');
            }
        }

        $payload = [
            'status'        => $approved ? LeaveStatus::Approved : LeaveStatus::Rejected,
            'is_paid'       => $isPaid,
            'decided_by'    => $request->user()->id,
            'decided_at'    => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ];

        // Jam final = punya CEO kalau dia isi, kalau nggak pakai usulan staf.
        // array_merge, BUKAN operator `+` — `+` gak nimpa key yang udah ada, dan
        // pola itu udah bikin bug NOT NULL berkali-kali di project ini.
        if ($approved && $leave->type->needsTime()) {
            $payload = array_merge($payload, [
                'start_time' => $data['start_time'] ?? $leave->start_time,
                'end_time'   => $data['end_time'] ?? $leave->end_time,
            ]);
        }

        $leave->update($payload);

        if ($approved && $leave->type->needsTime()) {
            $this->recomputeLateMinutes($leave->fresh());
        }

        return back()->with('ok', 'Pengajuan '.($approved ? 'disetujui' : 'ditolak').'.');
    }

    /**
     * Hitung ulang telat buat absen yang TERLANJUR tercatat sebelum ganti jadwal
     * disetujui.
     *
     * `late_minutes` itu kolom TERSIMPAN — dikunci saat clock-in, bukan dihitung ulang
     * tiap dibaca. Jadi kalau staf absen jam 10 pagi dan CEO baru menyetujui ganti
     * jadwalnya siang harinya, angka telatnya terlanjur 60 menit dan gak ikut berubah
     * sendiri. Tanpa method ini, orang tetap kecatat TELAT padahal izinnya disetujui.
     *
     * Rentangnya beberapa hari doang, jadi loop kecil — aman.
     *
     * ⚠️ Belum ada kebalikannya: kalau ganti jadwal disetujui lalu DIHAPUS, telatnya
     * gak balik sendiri. Koreksi manual ada di Rekap Absensi.
     */
    protected function recomputeLateMinutes(LeaveRequest $leave): void
    {
        if (! $leave->start_time) {
            return;
        }

        $attendances = \App\Models\Attendance::where('user_id', $leave->user_id)
            ->whereBetween('work_date', [$leave->date_from, $leave->date_to])
            ->whereNotNull('clock_in_at')
            ->get();

        foreach ($attendances as $a) {
            if ($a->is_off_day) {
                continue; // hari libur gak kenal telat
            }

            $scheduled = $a->work_date->copy()->setTimeFromTimeString($leave->start_time);

            $a->update([
                'late_minutes' => $a->clock_in_at->greaterThan($scheduled)
                    ? (int) $scheduled->diffInMinutes($a->clock_in_at)
                    : 0,
            ]);
        }
    }

    /**
     * Hapus pengajuan (soft) — CEO only. Cuti Approved yang dihapus otomatis
     * hilang dari kalender DAN tak lagi dibaca resolver absensi → hari-hari itu
     * kembali dihitung Alpha. Koreksi manual tersedia di Rekap Absensi.
     */
    public function destroy(Request $request, LeaveRequest $leave)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa menghapus pengajuan.');

        $wasApproved = $leave->status === LeaveStatus::Approved;
        $name        = $leave->user->name;

        $leave->delete();

        $msg = "Pengajuan {$name} dihapus.";
        if ($wasApproved) {
            $msg .= ' ⚠️ Hari-hari cuti tersebut kini dihitung ALPHA — koreksi di Rekap Absensi bila perlu.';
        }

        return back()->with('ok', $msg);
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa melihat sampah.');

        return view('leaves.trash', [
            'trashed' => LeaveRequest::onlyTrashed()->with('user')
                ->orderByDesc('deleted_at')->paginate(15),
        ]);
    }

    public function restore(Request $request, int $id)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa memulihkan.');

        $leave = LeaveRequest::onlyTrashed()->findOrFail($id);
        $leave->restore();

        $msg = "Pengajuan {$leave->user->name} dipulihkan.";
        if ($leave->status === LeaveStatus::Approved) {
            $msg .= ' Hari-harinya kembali dihitung sesuai status semula (tidak lagi Alpha).';
        }

        return back()->with('ok', $msg);
    }

    public function forceDelete(Request $request, int $id)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa menghapus permanen.');

        $leave = LeaveRequest::onlyTrashed()->findOrFail($id);
        $name  = $leave->user->name;
        $leave->forceDelete();

        return back()->with('ok', "Pengajuan {$name} dihapus permanen — tidak bisa dipulihkan lagi.");
    }

    public function clearTrash(Request $request)
    {
        abort_unless($request->user()->role->isCeo(), 403, 'Hanya CEO yang bisa mengosongkan sampah.');

        $count = LeaveRequest::onlyTrashed()->count();
        LeaveRequest::onlyTrashed()->forceDelete();

        return back()->with('ok', "{$count} pengajuan di sampah dihapus permanen.");
    }
}