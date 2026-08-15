<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withCommands([
        \App\Console\Commands\SendAdaptiveReminders::class,
        \App\Console\Commands\MakeAdmin::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(
            \Illuminate\Http\Middleware\HandleCors::class,
        );
        $middleware->alias([
            'role'     => \App\Http\Middleware\CheckRole::class,
            'approved' => \App\Http\Middleware\EnsureApproved::class,
        ]);
        $middleware->statefulApi();

        // TAMBAH: API request yang tidak terautentikasi
        // kembalikan JSON bukan redirect ke route 'login'
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // return null = kembalikan 401 JSON
            }
            return route('login'); // hanya untuk web
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // TAMBAH: handle unauthenticated exception untuk API
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            Request $request
        ) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Silakan login kembali.',
                ], 401);
            }
        });
    })->create();