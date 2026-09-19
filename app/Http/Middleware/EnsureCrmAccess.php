<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\UserRole;

class EnsureCrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $allowed = [
            UserRole::Ceo,
            UserRole::KepalaToko,
            UserRole::Frontliner,
            UserRole::AdminChat,
        ];

        if (!in_array($user->role, $allowed, true)) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}