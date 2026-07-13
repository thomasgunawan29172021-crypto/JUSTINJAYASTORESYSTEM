<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsManager
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role->isManager(), 403, 'Menu ini khusus Kepala Toko / CEO.');

        return $next($request);
    }
}