<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Proteksi route guru agar siswa tidak bisa akses.
     *
     * Cara daftarkan di bootstrap/app.php (Laravel 11):
     *   ->withMiddleware(function (Middleware $middleware) {
     *       $middleware->alias(['role' => \App\Http\Middleware\CheckRole::class]);
     *   })
     *
     * Atau di app/Http/Kernel.php (Laravel 10) di $routeMiddleware:
     *   'role' => \App\Http\Middleware\CheckRole::class,
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || $request->user()->role !== $role) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya ' . $role . ' yang dapat mengakses halaman ini.',
            ], 403);
        }

        return $next($request);
    }
}