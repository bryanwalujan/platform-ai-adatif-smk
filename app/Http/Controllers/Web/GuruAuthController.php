<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuruAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isTeacher() && ! Auth::user()->isPending()) {
            return redirect()->route('guru.dashboard');
        }

        return view('guru.auth.login');
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

        if (! $user->isTeacher()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun ini bukan akun guru.'])->onlyInput('email');
        }

        // Ditolak di titik login (bukan cuma diserahkan ke middleware 'approved')
        // supaya guru langsung dapat pesan yang jelas, bukan nyangkut di 403
        // setelah sesi sudah terlanjur aktif.
        if ($user->status === 'rejected') {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun guru ini ditolak admin. Hubungi admin sekolah.'])->onlyInput('email');
        }

        if ($user->isPending()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun guru Anda masih menunggu persetujuan admin.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('guru.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('guru.login');
    }
}