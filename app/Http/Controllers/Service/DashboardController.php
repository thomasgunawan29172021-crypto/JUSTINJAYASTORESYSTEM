<?php

namespace App\Http\Controllers\Service;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Satu dashboard, antrian beda per role:
     * - Teknisi    : antrian unit per tahap pengerjaan
     * - Admin chat : perlu konfirmasi biaya + perlu dikabari + follow-up
     * - Frontliner : intake hari ini + checkout
     * - Role lain (CEO, kepala toko, gudang, dst) : ringkasan manajer
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role; // sudah enum UserRole karena cast di model — TIDAK perlu UserRole::from()

        $scope = ServiceTicket::query()->with(['branch', 'technician']);
        // CATATAN FASE PERMISSION: scoping per cabang sengaja belum dipasang.
        // Nanti: ->when(..., fn ($q) => $q->where('branch_id', $user->branch_id))

        $counts = (clone $scope)
            ->where('status', '!=', TicketStatus::Selesai->value)
            ->get()
            ->groupBy(fn ($t) => $t->status->value)
            ->map->count();

        $queues = match ($role) {
            UserRole::Teknisi => [
                'Antrian pengecekan' => (clone $scope)->where('status', TicketStatus::Diterima->value)
                    ->where(fn ($q) => $q->whereNull('technician_id')->orWhere('technician_id', $user->id))
                    ->oldest('checked_in_at')->get(),
                'Sedang diagnosa' => (clone $scope)->where('status', TicketStatus::Diagnosa->value)
                    ->where('technician_id', $user->id)->get(),
                'Siap dikerjakan / dikerjakan' => (clone $scope)
                    ->whereIn('status', [TicketStatus::Dikerjakan->value, TicketStatus::MenungguSparepart->value])
                    ->where('technician_id', $user->id)->get(),
                'QC / Testing' => (clone $scope)->where('status', TicketStatus::Qc->value)
                    ->where('technician_id', $user->id)->get(),
            ],
            UserRole::AdminChat => [
                'Perlu konfirmasi biaya ke customer' => (clone $scope)
                    ->where('status', TicketStatus::MenungguKonfirmasi->value)
                    ->oldest('updated_at')->get(),
                'Siap diambil — belum dikabari' => (clone $scope)
                    ->where('status', TicketStatus::SiapDiambil->value)
                    ->whereNull('notified_at')->get(),
                'Follow-up: belum diambil > 3 hari' => (clone $scope)
                    ->where('status', TicketStatus::SiapDiambil->value)
                    ->where('completed_at', '<', now()->subDays(3))->get(),
            ],
            UserRole::Frontliner => [
                'Masuk hari ini' => (clone $scope)
                    ->whereDate('checked_in_at', today())->latest('checked_in_at')->get(),
                'Siap diambil (proses checkout di sini)' => (clone $scope)
                    ->where('status', TicketStatus::SiapDiambil->value)
                    ->oldest('completed_at')->get(),
                'Dibatalkan — unit dikembalikan' => (clone $scope)
                    ->where('status', TicketStatus::Dibatalkan->value)->get(),
            ],
            default => [
                'Macet > 7 hari' => (clone $scope)
                    ->where('status', '!=', TicketStatus::Selesai->value)
                    ->where('checked_in_at', '<', now()->subDays(7))
                    ->oldest('checked_in_at')->get(),
                'Menunggu konfirmasi customer' => (clone $scope)
                    ->where('status', TicketStatus::MenungguKonfirmasi->value)->get(),
                'Siap diambil' => (clone $scope)
                    ->where('status', TicketStatus::SiapDiambil->value)->get(),
            ],
        };

        /* ---------- PERLU PERHATIAN (pola sama dengan dashboard marketplace) ---------- */
        $alerts = collect();

        $openNotCancelled = [
            TicketStatus::Diterima->value, TicketStatus::Diagnosa->value,
            TicketStatus::MenungguKonfirmasi->value, TicketStatus::MenungguSparepart->value,
            TicketStatus::Dikerjakan->value, TicketStatus::Qc->value,
        ];

        // 1. Tiket macet (nginap lama, belum sampai siap-diambil)
        $macet7 = (clone $scope)->whereIn('status', $openNotCancelled)
            ->where('checked_in_at', '<', now()->subDays(7))->count();
        $macet3 = (clone $scope)->whereIn('status', $openNotCancelled)
            ->where('checked_in_at', '<', now()->subDays(3))
            ->where('checked_in_at', '>=', now()->subDays(7))->count();

        if ($macet7 > 0) {
            $tertua = (clone $scope)->whereIn('status', $openNotCancelled)->oldest('checked_in_at')->first();
            $hari   = (int) $tertua->checked_in_at->diffInDays(now());
            $alerts->push(['level' => 'red',
                'msg' => "{$macet7} unit nginap ≥ 7 hari (terlama {$hari} hari: {$tertua->device_brand} {$tertua->device_model} — {$tertua->ticket_number})."]);
        } elseif ($macet3 > 0) {
            $alerts->push(['level' => 'yellow', 'msg' => "{$macet3} unit nginap 3–6 hari — pantau."]);
        }

        // 2. Siap diambil tapi customer belum ambil > 3 hari
        $belumDiambil = (clone $scope)->where('status', TicketStatus::SiapDiambil->value)
            ->where('completed_at', '<', now()->subDays(3))->count();
        if ($belumDiambil > 0) {
            $alerts->push(['level' => 'yellow', 'msg' => "{$belumDiambil} unit siap diambil > 3 hari belum dijemput customer — follow up."]);
        }

        // 3. Siap diambil belum dikabari sama sekali
        $belumDikabari = (clone $scope)->where('status', TicketStatus::SiapDiambil->value)
            ->whereNull('notified_at')->count();
        if ($belumDikabari > 0) {
            $alerts->push(['level' => $belumDikabari >= 3 ? 'red' : 'yellow',
                'msg' => "{$belumDikabari} unit selesai tapi customer BELUM dikabari."]);
        }

        // 4. Menunggu konfirmasi biaya ngendon > 2 hari (macet di pintu admin chat)
        $konfirmasiLama = (clone $scope)->where('status', TicketStatus::MenungguKonfirmasi->value)
            ->where('updated_at', '<', now()->subDays(2))->count();
        if ($konfirmasiLama > 0) {
            $alerts->push(['level' => 'red',
                'msg' => "{$konfirmasiLama} tiket menunggu konfirmasi biaya > 2 hari — customer belum dihubungi/belum jawab."]);
        }

        return view('service.dashboard', [
            'role'   => $role,
            'counts' => $counts,
            'queues' => $queues,
            'alerts' => $alerts,
        ]);
    }
}