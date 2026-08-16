<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom buat alur verifikasi email pakai kode 6-digit (bukan link) —
     * dikirim saat register, dicek di EmailVerificationController.
     * `email_verified_at` sendiri sudah ada dari migration users bawaan
     * Laravel, tinggal dipakai sebagai penanda "sudah verifikasi".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code', 6)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code');
        });

        // PENTING: semua user yang SUDAH ADA sebelum fitur ini harus otomatis
        // dianggap terverifikasi — kalau tidak, mereka semua mendadak tidak
        // bisa login sama sekali (login() sekarang menolak email_verified_at
        // yang masih null) begitu migration ini jalan di produksi.
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_code', 'email_verification_code_expires_at']);
        });
    }
};
