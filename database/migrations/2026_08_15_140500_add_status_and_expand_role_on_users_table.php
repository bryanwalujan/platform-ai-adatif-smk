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
     * - `role`: enum diperluas dari ('siswa','guru') jadi ('siswa','guru','admin').
     *   Dipakai raw DB::statement karena Blueprint::change() untuk MySQL enum
     *   butuh doctrine/dbal yang tidak terinstall di project ini.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'rejected'])
                  ->default('active')->after('role');
        });

        // Raw ALTER hanya valid syntax MySQL — di-guard supaya migration ini tidak
        // pecah kalau suatu saat dijalankan di driver lain (mis. sqlite di test suite,
        // lihat phpunit.xml). Produksi selalu mysql (lihat .env), jadi ini yang jalan.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('siswa','guru','admin') NOT NULL DEFAULT 'siswa'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Turunkan role admin ke siswa dulu supaya enum lama tidak menolak baris ini
        DB::table('users')->where('role', 'admin')->update(['role' => 'siswa']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('siswa','guru') NOT NULL DEFAULT 'siswa'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
