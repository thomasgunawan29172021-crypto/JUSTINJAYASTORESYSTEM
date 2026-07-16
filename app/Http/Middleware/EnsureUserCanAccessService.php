<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserCanAccessService
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role->canAccessService(), 403,
            'Anda tidak punya akses ke modul Service.');

        return $next($request);
    }
}
