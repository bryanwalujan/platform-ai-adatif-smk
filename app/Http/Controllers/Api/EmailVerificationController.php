<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailVerificationController extends Controller
{
    /**
     * POST /email/verify
     * Cek kode 6-digit yang dikirim saat register. Sukses -> tandai
     * email_verified_at, hapus kode, langsung buatkan token (ini momen
     * "login" pertama kali — register() sendiri sengaja TIDAK memberi
     * token sebelum email diverifikasi).
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi, silakan login'], 422);
        }

        if (! $user->email_verification_code
            || $user->email_verification_code !== $validated['code']
            || ! $user->email_verification_code_expires_at
            || $user->email_verification_code_expires_at->isPast()
        ) {
            return response()->json(['message' => 'Kode salah atau sudah kedaluwarsa'], 422);
        }

        $user->update([
            'email_verified_at'                   => now(),
            'email_verification_code'             => null,
            'email_verification_code_expires_at'  => null,
        ]);

        // Token lama (kalau ada sisa dari percobaan sebelumnya) dibersihkan dulu
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email berhasil diverifikasi',
            'token'   => $token,
            'user'    => $user->toAuthArray(),
        ]);
    }

    /**
     * POST /email/resend
     * Kirim ulang kode verifikasi — dipakai kalau kode lama kedaluwarsa
     * atau email pertama tidak sampai. Dibatasi 60 detik antar pengiriman
     * supaya tidak disalahgunakan buat spam ke kotak masuk orang lain.
     */
    public function resend(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi, silakan login'], 422);
        }

        if ($user->email_verification_code_expires_at
            && $user->email_verification_code_expires_at->subMinutes(15 - 1)->isFuture()
        ) {
            return response()->json(['message' => 'Tunggu sebentar sebelum minta kode baru'], 429);
        }

        $this->sendVerificationCode($user);

        return response()->json(['message' => 'Kode baru sudah dikirim ke email Anda']);
    }

    /**
     * Generate kode 6-digit + kirim email — dipakai AuthController::register()
     * dan resend() di sini.
     */
    public static function sendVerificationCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code'            => $code,
            'email_verification_code_expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new OtpCodeMail($user->name, $code, 'verification'));
    }
}
