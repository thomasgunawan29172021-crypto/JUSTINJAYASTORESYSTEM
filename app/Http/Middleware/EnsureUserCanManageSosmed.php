<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserCanManageSosmed
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->canManageSosmed(), 403, 'Khusus PIC Sosmed atau CEO.');

        return $next($request);
    }
}
