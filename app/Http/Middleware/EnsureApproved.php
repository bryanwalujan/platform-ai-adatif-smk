<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    /**
     * Blokir akses ke route guru selama akunnya belum di-approve admin
     * (status masih 'pending') atau sudah ditolak ('rejected').
     *
     * Dipasang bareng middleware `role:guru` di grup route guru — login
     * tetap diizinkan untuk status apapun (lihat AuthController::login),
     * ini adalah lapisan keamanan server-side untuk aksi guru sesungguhnya,
     * bukan sekadar UX di Flutter.
     *
     * Daftarkan di bootstrap/app.php:
     *   $middleware->alias(['approved' => \App\Http\Middleware\EnsureApproved::class]);
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active') {
            $message = $user?->status === 'pending'
                ? 'Akun Anda masih menunggu persetujuan admin.'
                : 'Akun Anda tidak aktif. Silakan hubungi admin.';

            // Panel web guru (routes/web.php, guard session) mengharapkan
            // halaman/redirect, bukan JSON — beda dari klien API (Flutter,
            // selalu kirim Accept: application/json). Pola sama seperti
            // CheckRole, supaya konsisten di seluruh middleware otorisasi.
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}