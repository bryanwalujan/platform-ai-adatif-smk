<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login terpadu untuk panel web (admin & guru) — satu form, satu route
 * ('login' / 'login.submit'), redirect tujuan ditentukan dari role user
 * SETELAH kredensial tervalidasi, bukan dari URL yang diakses.
 *
 * Menggantikan AdminAuthController & GuruAuthController yang sebelumnya
 * masing-masing punya form/route login sendiri — sekarang keduanya cuma
 * dipakai untuk logout (masih dipisah karena beda redirect tujuan setelah
 * logout: balik ke halaman ini juga sebenarnya, jadi logout pun bisa
 * disatukan, lihat method logout() di bawah).
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->redirectPathFor(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        $user = Auth::user();

        // Siswa tidak punya panel web — mereka pakai aplikasi Flutter
        // (API Sanctum, routes/api.php). Ditolak di sini supaya pesannya
        // jelas, bukan celah lolos-tapi-403 di tengah jalan.
        if ($user->isStudent()) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Akun siswa tidak memiliki akses ke panel web. Silakan gunakan aplikasi mobile.',
            ])->onlyInput('email');
        }

        // Guru wajib disetujui admin dulu — dicek di titik login supaya
        // pesannya spesifik, bukan 403 mentah dari middleware 'approved'.
        if ($user->isTeacher()) {
            if ($user->status === 'rejected') {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun guru ini ditolak admin. Hubungi admin sekolah.',
                ])->onlyInput('email');
            }

            if ($user->isPending()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun guru Anda masih menunggu persetujuan admin.',
                ])->onlyInput('email');
            }
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPathFor($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectPathFor($user): string
    {
        return match (true) {
            $user->isAdmin()   => route('admin.dashboard'),
            $user->isTeacher() => route('guru.dashboard'),
            default            => route('login'),
        };
    }
}