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

        // TAMBAH: abort(403/422/...) & throw HttpException (dipakai luas oleh
        // SubjectAccessService & controller lain untuk otorisasi mata pelajaran)
        // harus tetap JSON di jalur API meskipun klien tidak kirim header
        // `Accept: application/json` secara eksplisit — sebelumnya balik jadi
        // halaman error HTML default Laravel untuk request semacam itu.
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\HttpException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Request tidak dapat diproses.',
                ], $e->getStatusCode());
            }
        });

        // TAMBAH: ValidationException (dari $request->validate() DAN throw
        // manual seperti di AuthController::login()) — sama seperti
        // HttpException di atas, defaultnya Laravel REDIRECT (302) untuk
        // request yang tidak eksplisit kirim Accept: application/json.
        // Flutter (Dio) di app ini TIDAK PERNAH kirim header itu — jadi
        // SEMUA validasi gagal di SELURUH endpoint selama ini kemungkinan
        // balik jadi HTML redirect ke Flutter, bukan pesan error yang jelas.
        // Ditemukan saat testing alur verifikasi email (2026-08-17).
        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], $e->status);
            }
        });
    })->create();