<?php

namespace App\Http\Controllers\Service;

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /* ------------------------------- Daftar ------------------------------- */

    public function index(Request $request)
    {
        $tickets = ServiceTicket::query()
            ->with(['branch', 'technician'])
            ->when($request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = trim($request->string('q'));
                $q->where(fn ($qq) => $qq
                    ->where('ticket_number', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', '%'.preg_replace('/\D+/', '', $s).'%')
                    ->orWhere('device_model', 'like', "%{$s}%"));
            })
            ->orderByDesc('checked_in_at')
            ->paginate(20)
            ->withQueryString();

        return view('service.tickets.index', [
            'tickets'  => $tickets,
            'statuses' => TicketStatus::cases(),
        ]);
    }

    /* ------------------------------- Intake ------------------------------- */

    public function create()
    {
        return view('service.tickets.create', [
            'branches'    => Branch::where('has_service', true)->get(),
            'technicians' => User::where('role', UserRole::Teknisi->value)->where('is_active', true)->get(),
            'admins'      => User::where('role', UserRole::AdminChat->value)->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'          => ['required', 'exists:branches,id'],
            'customer_name'      => ['required', 'string', 'max:100'],
            'customer_phone'     => ['required', 'string', 'max:30'],
            'customer_phone_alt' => ['nullable', 'string', 'max:30'],
            'device_brand'       => ['required', 'string', 'max:50'],
            'device_model'       => ['required', 'string', 'max:100'],
            'imei'               => ['nullable', 'string', 'max:40'],
            'device_passcode'    => ['nullable', 'string', 'max:100'],
            'complaint'          => ['required', 'string'],
            'physical_condition' => ['nullable', 'array'],
            'accessories'        => ['nullable', 'array'],
            'estimated_done_at'  => ['nullable', 'date'],
            'technician_id'      => ['nullable', 'exists:users,id'],
            'admin_id'           => ['nullable', 'exists:users,id'],
            'warranty_days'      => ['nullable', 'integer', 'min:0', 'max:365'],
            'parent_ticket_id'   => ['nullable', 'exists:service_tickets,id'],
            'notes'              => ['nullable', 'string'],
            'photos'             => ['nullable', 'array', 'max:6'],
            'photos.*'           => ['image', 'max:4096'],
        ]);

        $ticket = ServiceTicket::open($data, $request->user());

        foreach ($request->file('photos', []) as $photo) {
            $ticket->photos()->create([
                'type'        => 'intake',
                'path'        => $photo->store("tickets/{$ticket->id}", config('filesystems.default')),
                'uploaded_by' => $request->user()->id,
                'created_at'  => now(),
            ]);
        }

        return redirect()
            ->route('service.tickets.show', $ticket)
            ->with('ok', "Tiket {$ticket->ticket_number} dibuat. Nota siap dicetak.");
    }

    /* ------------------------------- Detail ------------------------------- */

    public function show(ServiceTicket $ticket)
    {
        $ticket->load([
            'branch', 'technician', 'admin', 'creator',
            'histories.user', 'photos', 'parts', 'notifications.user',
            'parentTicket', 'warrantyClaims',
        ]);

        return view('service.tickets.show', [
            'ticket'      => $ticket,
            'technicians' => User::where('role', UserRole::Teknisi->value)->where('is_active', true)->get(),
            'admins'      => User::where('role', UserRole::AdminChat->value)->where('is_active', true)->get(),
        ]);
    }

    /* --------------------------- Transisi status --------------------------- */

    public function transition(Request $request, ServiceTicket $ticket)
    {
        $data = $request->validate([
            'status'         => ['required', Rule::enum(TicketStatus::class)],
            'note'           => ['nullable', 'string', 'max:500'],
            'diagnosis'      => ['nullable', 'string'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'approved_cost'  => ['nullable', 'integer', 'min:0'],
            'final_cost'     => ['nullable', 'integer', 'min:0'],
            'cancel_reason'  => ['nullable', 'string', 'max:500'],
        ]);

        $to = TicketStatus::from($data['status']);

        // CATATAN FASE PERMISSION: cek role per transisi (dulu allowedRoles())
        // sengaja belum dipasang — authorization diratakan dulu.

        match ($to) {
            TicketStatus::MenungguKonfirmasi => $ticket->fill([
                'diagnosis'      => $data['diagnosis'] ?? $ticket->diagnosis,
                'estimated_cost' => $data['estimated_cost'] ?? $ticket->estimated_cost,
            ]),
            TicketStatus::Dikerjakan => $ticket->fill([
                // Jangan timpa approved_cost yang sudah ada (mis. balik dari Menunggu Sparepart)
                'approved_cost' => $data['approved_cost'] ?? $ticket->approved_cost ?? $ticket->estimated_cost,
            ]),
            TicketStatus::Selesai => $ticket->fill([
                'final_cost' => $data['final_cost'] ?? $ticket->approved_cost,
            ]),
            TicketStatus::Dibatalkan => $ticket->fill([
                'cancel_reason' => $data['cancel_reason'] ?? null,
            ]),
            default => null,
        };

        try {
            $ticket->transitionTo($to, $request->user(), $data['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('ok', "Status diubah ke: {$to->label()}");
    }

    /* --------------------- Checklist kabari (pengganti WA) --------------------- */

    public function notify(Request $request, ServiceTicket $ticket)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(NotificationType::class)],
        ]);

        $type = NotificationType::from($data['type']);
        $ticket->markNotified($type, $request->user());

        return back()->with('ok', "Checklist \"{$type->label()}\" dicatat.");
    }

    /* ------------------------ Penugasan & pendukung ------------------------ */

    public function assign(Request $request, ServiceTicket $ticket)
    {
        $data = $request->validate([
            'technician_id' => ['nullable', 'exists:users,id'],
            'admin_id'      => ['nullable', 'exists:users,id'],
        ]);

        $ticket->update($data);

        return back()->with('ok', 'Penugasan diperbarui.');
    }

    public function storePart(Request $request, ServiceTicket $ticket)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'qty'   => ['required', 'integer', 'min:1'],
            'cost'  => ['required', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $ticket->parts()->create($data + ['added_by' => $request->user()->id]);

        return back()->with('ok', 'Sparepart dicatat.');
    }

    public function destroyPart(ServiceTicket $ticket, int $partId)
    {
        $ticket->parts()->whereKey($partId)->delete();

        return back()->with('ok', 'Sparepart dihapus.');
    }

    /** Nota servis untuk dicetak (berisi QR ke halaman tracking). */
    public function receipt(ServiceTicket $ticket)
    {
        $ticket->load(['branch', 'creator', 'photos']);

        return view('service.tickets.receipt', ['ticket' => $ticket]);
    }
}