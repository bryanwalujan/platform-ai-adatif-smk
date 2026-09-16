<?php

namespace App\Http\Middleware;

use App\Support\SchoolContext;
use Closure;
use Illuminate\Http\Request;

class UseSchool
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->school_id && $user->school?->is_active, 403, 'Sekolah akun tidak aktif atau belum ditetapkan.');
        abort_if($user->status === 'rejected', 403, 'Akun tidak aktif. Hubungi admin sekolah.');

        return app(SchoolContext::class)->run((int) $user->school_id, fn () => $next($request));
    }
}
