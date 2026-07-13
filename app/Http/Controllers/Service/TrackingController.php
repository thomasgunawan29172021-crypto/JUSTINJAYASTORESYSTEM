<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TrackingController extends Controller
{
    public function form()
    {
        return view('service.track.form');
    }

    /**
     * Validasi ganda: nomor servis + nomor HP harus cocok.
     * Rate limit per IP supaya tidak bisa dipakai menebak-nebak tiket orang lain.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'ticket_number' => ['required', 'string', 'max:30'],
            'phone'         => ['required', 'string', 'max:30'],
        ]);

        $key = 'track:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'ticket_number' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }
        RateLimiter::hit($key, 300);

        $ticket = ServiceTicket::query()
            ->where('ticket_number', strtoupper(trim($data['ticket_number'])))
            ->where('customer_phone', ServiceTicket::normalizePhone($data['phone']))
            ->first();

        if (! $ticket) {
            return back()
                ->withInput()
                ->withErrors(['ticket_number' => 'Nomor servis dan nomor HP tidak cocok. Periksa kembali nota Anda.']);
        }

        RateLimiter::clear($key);

        return redirect()->route('track.show', ['ticket' => $ticket->ticket_number, 't' => $ticket->tracking_token]);
    }

    /** Halaman status — diakses via hasil lookup atau scan QR di nota. */
    public function show(Request $request, string $ticket)
    {
        $ticket = ServiceTicket::query()
            ->where('ticket_number', $ticket)
            ->where('tracking_token', (string) $request->query('t'))
            ->with(['branch', 'histories'])
            ->firstOrFail();

        return view('service.track.show', ['ticket' => $ticket]);
    }
}