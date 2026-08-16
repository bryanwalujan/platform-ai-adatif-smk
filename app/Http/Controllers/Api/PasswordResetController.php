<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    /** Kode reset berlaku berapa lama */
    private const EXPIRES_MINUTES = 15;

    /**
     * POST /password/forgot
     * Kirim kode 6-digit ke email — dipakai tabel password_reset_tokens
     * bawaan Laravel (biasanya buat link, di sini dipakai buat kode
     * pendek), 1 baris per email (upsert kalau minta lagi sebelum expired).
     */
    public function forgot(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Email tidak terdaftar'], 404);
        }

        $existing = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        if ($existing && now()->diffInSeconds($existing->created_at) < 60) {
            return response()->json(['message' => 'Tunggu sebentar sebelum minta kode baru'], 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        Mail::to($user->email)->send(new OtpCodeMail($user->name, $code, 'password_reset'));

        return response()->json(['message' => 'Kode reset password sudah dikirim ke email Anda']);
    }

    /**
     * POST /password/reset
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (! $record || ! Hash::check($validated['code'], $record->token)) {
            return response()->json(['message' => 'Kode salah atau tidak ditemukan'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > self::EXPIRES_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json(['message' => 'Kode sudah kedaluwarsa, minta kode baru'], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan'], 404);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        // Kode cuma sekali pakai + putus semua sesi lama (jaga-jaga akun dicuri)
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil direset, silakan login dengan password baru']);
    }
}
