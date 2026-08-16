<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /register
     *
     * PERBAIKAN: tidak lagi langsung memberi token/auto-login. Kode
     * verifikasi 6-digit dikirim ke email dulu — akun baru cuma bisa
     * login (dapat token) setelah verifikasi lewat
     * EmailVerificationController::verify(). Login harian setelahnya
     * TIDAK perlu kode apapun lagi, cukup email+password seperti biasa.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            // 'admin' sengaja tidak diizinkan lewat register — akun admin
            // hanya dibuat lewat `php artisan make:admin` di server.
            'role'     => 'nullable|in:siswa,guru', // default: siswa
        ]);

        $role = $validated['role'] ?? 'siswa';

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $role,
            // Guru baru menunggu approval admin dulu sebelum bisa pakai fitur
            // guru (lihat middleware EnsureApproved) — siswa langsung aktif
            // begitu email-nya diverifikasi. Dua gate independen: verifikasi
            // email (semua role) lalu approval admin (guru saja).
            'status'   => $role === 'guru' ? 'pending' : 'active',
        ]);

        EmailVerificationController::sendVerificationCode($user);

        return response()->json([
            'message' => 'Akun berhasil dibuat. Kode verifikasi sudah dikirim ke email Anda.',
            'email'   => $user->email,
        ], 201);
    }

    /**
     * POST /login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Belum verifikasi email -> belum boleh dapat token. Balikan 403
        // dengan flag khusus supaya Flutter tahu harus arahkan ke layar
        // verifikasi (bukan sekadar "email/password salah").
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message'          => 'Email belum diverifikasi. Cek kotak masuk email Anda.',
                'needs_verification' => true,
                'email'            => $user->email,
            ], 403);
        }

        // Hapus token lama agar tidak menumpuk
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->toAuthArray(),
        ]);
    }

    /**
     * GET /me
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->toAuthArray());
    }

    /**
     * POST /logout
     * BARU: invalidate token yang sedang dipakai
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar']);
    }

    /**
     * PUT /profile
     * BARU: update nama & email
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user'    => $user->fresh()->toAuthArray(),
        ]);
    }

    /**
     * POST /profile/photo
     * BARU: upload foto profil
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:2048', // max 2MB
        ]);

        $user = $request->user();

        // Hapus foto lama jika ada
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user->update(['photo_path' => $path]);

        return response()->json([
            'message'   => 'Foto profil berhasil diperbarui',
            'photo_url' => Storage::url($path),
        ]);
    }
}