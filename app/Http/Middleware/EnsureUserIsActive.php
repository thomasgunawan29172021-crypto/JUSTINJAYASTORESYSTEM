<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    /** Tendang user yang akunnya dinonaktifkan, walau session-nya masih hidup. */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi CEO.']);
        }

        return $next($request);
    }
}