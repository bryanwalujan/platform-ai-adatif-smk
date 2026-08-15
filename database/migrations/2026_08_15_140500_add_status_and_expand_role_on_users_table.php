<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - `status`: dipakai untuk alur approval akun guru ('pending' sampai
     *   di-approve admin). Default 'active' supaya semua user existing
     *   (siswa + guru lama) tidak terkunci saat migration ini jalan.
     * - `role`: enum diperluas dari ('siswa','guru') jadi ('siswa','guru','admin')
     *   lewat Blueprint::change() — Laravel 12 sudah native mendukung ini untuk
     *   MySQL/SQLite/dst tanpa doctrine/dbal, jadi portable lintas driver
     *   (penting karena test suite project ini pakai sqlite, lihat phpunit.xml).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'rejected'])
                  ->default('active')->after('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['siswa', 'guru', 'admin'])->default('siswa')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Turunkan role admin ke siswa dulu supaya enum lama tidak menolak baris ini
        DB::table('users')->where('role', 'admin')->update(['role' => 'siswa']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['siswa', 'guru'])->default('siswa')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
