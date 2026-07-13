<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function attempt(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Kunci gabungan email+IP: mencegah brute-force ke 1 akun spesifik,
        // sekaligus tidak mengunci akun orang lain kalau 1 IP nyerang banyak email.
        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput(['email' => $request->input('email')])
                ->withErrors(['email' => "Terlalu banyak percobaan gagal. Coba lagi dalam {$seconds} detik."]);
        }

        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        // Setiap kegagalan menambah hitungan; kunci 60 detik setelah 5x gagal
        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withInput(['email' => $request->input('email')])
            ->withErrors(['email' => 'Email atau password salah, atau akun nonaktif.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}