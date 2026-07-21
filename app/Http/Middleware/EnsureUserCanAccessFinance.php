<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserCanAccessFinance
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->canAccessFinance(), 403, 'Menu ini khusus CEO / Kepala Keuangan.');

        return $next($request);
    }
}
