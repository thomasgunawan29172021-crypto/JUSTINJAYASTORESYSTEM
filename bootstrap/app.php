<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Belum login tapi akses halaman internal → lempar ke /login
        $middleware->redirectGuestsTo(fn () => route('login'));
        // Sudah login tapi buka /login lagi → lempar ke dashboard
        $middleware->redirectUsersTo('/dashboard');

        $middleware->alias([
            'ceo'     => \App\Http\Middleware\EnsureUserIsCeo::class,
            'active'  => \App\Http\Middleware\EnsureUserIsActive::class,
            'manager' => \App\Http\Middleware\EnsureUserIsManager::class,
            'finance' => \App\Http\Middleware\EnsureUserCanAccessFinance::class,
            'sosmed'  => \App\Http\Middleware\EnsureUserCanManageSosmed::class,
            'service' => \App\Http\Middleware\EnsureUserCanAccessService::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
