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
            'pendings' => LeaveRequest::with('user.branch')
                ->where('status', LeaveStatus::Pending->value)
                ->orderBy('date_from')->get(),
            'recents'  => LeaveRequest::with(['user', 'decider'])
                ->where('status', '!=', LeaveStatus::Pending->value)
                ->orderByDesc('decided_at')->limit(15)->get(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate([
            'decision'      => ['required', Rule::in(['approve', 'reject'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
            'is_paid'       => ['nullable', 'boolean'],
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

        $leave->update([
            'status'        => $approved ? LeaveStatus::Approved : LeaveStatus::Rejected,
            'is_paid'       => $isPaid,
            'decided_by'    => $request->user()->id,
            'decided_at'    => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        return back()->with('ok', 'Pengajuan '.($approved ? 'disetujui' : 'ditolak').'.');
    }
}