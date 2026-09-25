<?php
namespace App\Http\Middleware;
use Closure;use Illuminate\Http\Request;
class EnsureUserCanAccessCrm {public function handle(Request $request,Closure $next){abort_unless($request->user()?->canAccessCrm(),403,'Anda tidak punya akses ke modul CRM.');return $next($request);}}
