<?php

namespace App\Http\Controllers\Warranty;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use App\Models\WarrantyClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Lacak publik — kembaran TrackingController servis: nomor + HP, rate limit, token QR. */
class WarrantyTrackingController extends Controller
{
    public function form()
    {
        return view('warranty.track.form');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'claim_number' => ['required', 'string', 'max:30'],
            'phone'        => ['required', 'string', 'max:30'],
        ]);

        $key = 'track-warranty:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'claim_number' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }
        RateLimiter::hit($key, 300);

        $claim = WarrantyClaim::query()
            ->where('claim_number', strtoupper(trim($data['claim_number'])))
            ->where('customer_phone', ServiceTicket::normalizePhone($data['phone']))
            ->first();

        if (! $claim) {
            return back()
                ->withInput()
                ->withErrors(['claim_number' => 'Nomor retur dan nomor HP tidak cocok. Periksa kembali nota Anda.']);
        }

        RateLimiter::clear($key);

        return redirect()->route('warranty.track.show', ['claim' => $claim->claim_number, 't' => $claim->tracking_token]);
    }

    public function show(Request $request, string $claim)
    {
        $claim = WarrantyClaim::query()
            ->where('claim_number', $claim)
            ->where('tracking_token', (string) $request->query('t'))
            ->with(['branch', 'product', 'histories'])
            ->firstOrFail();

        return view('warranty.track.show', ['claim' => $claim]);
    }
}
