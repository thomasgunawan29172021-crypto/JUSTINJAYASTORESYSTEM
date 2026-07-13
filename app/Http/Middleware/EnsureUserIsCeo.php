<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsCeo
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role->isCeo(), 403, 'Menu ini khusus CEO.');

        return $next($request);
    }
}