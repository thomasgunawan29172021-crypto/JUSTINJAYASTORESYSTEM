<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('leaves.index', [
            'requests'  => LeaveRequest::where('user_id', $user->id)->orderByDesc('created_at')->get(),
            'cutiUsed'  => LeaveRequest::cutiUsedDays($user->id, now()->year),
            'cutiQuota' => LeaveRequest::CUTI_QUOTA_DAYS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', Rule::enum(LeaveType::class)],
            'date_from'  => ['required', 'date'],
            'date_to'    => ['required', 'date', 'after_or_equal:date_from'],
            'reason'     => ['required', 'string', 'max:500'],
            // Sakit wajib lampirkan surat dokter (foto/scan)
            'attachment' => ['required_if:type,sakit', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ], [
            'attachment.required_if' => 'Pengajuan sakit wajib melampirkan surat dokter.',
        ]);

        $user = $request->user();
        $type = LeaveType::from($data['type']);

        if (LeaveRequest::overlapExists($user->id, $data['date_from'], $data['date_to'])) {
            return back()->withInput()->withErrors([
                'date_from' => 'Tanggal bentrok dengan pengajuan Anda yang masih menunggu / sudah disetujui.',
            ]);
        }

        // Kuota cuti: hitung terhadap tahun tanggal mulai
        if ($type === LeaveType::Cuti) {
            $year      = (int) date('Y', strtotime($data['date_from']));
            $requested = (new LeaveRequest($data))->days();
            $used      = LeaveRequest::cutiUsedDays($user->id, $year);

            if ($used + $requested > LeaveRequest::CUTI_QUOTA_DAYS) {
                $sisa = LeaveRequest::CUTI_QUOTA_DAYS - $used;

                return back()->withInput()->withErrors([
                    'date_from' => "Kuota cuti {$year} tidak cukup: sisa {$sisa} hari, diajukan {$requested} hari.",
                ]);
            }
        }

        LeaveRequest::create([
            'user_id'         => $user->id,
            'type'            => $type,
            'date_from'       => $data['date_from'],
            'date_to'         => $data['date_to'],
            'reason'          => $data['reason'],
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store("leaves/{$user->id}", config('filesystems.default'))
                : null,
        ]);

        return back()->with('ok', 'Pengajuan terkirim — menunggu persetujuan Kepala Toko / CEO.');
    }

    /** Batalkan pengajuan sendiri yang masih pending. */
    public function destroy(Request $request, LeaveRequest $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        if ($leave->status !== LeaveStatus::Pending) {
            return back()->withErrors(['leave' => 'Pengajuan yang sudah diputuskan tidak bisa dibatalkan.']);
        }

        if ($leave->attachment_path) {
            Storage::disk(config('filesystems.default'))->delete($leave->attachment_path);
        }
        $leave->delete();

        return back()->with('ok', 'Pengajuan dibatalkan.');
    }
}