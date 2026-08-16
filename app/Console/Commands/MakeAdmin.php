<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    /**
     * Sengaja dibuat sebagai command, BUKAN seeder — supaya aman dijalankan
     * langsung di produksi (seeder lain di project ini, mis. UserSeeder,
     * melakukan truncate dan tidak boleh disentuh di prod).
     *
     * Pemakaian: php artisan make:admin
     * (mode interaktif — akan tanya nama/email/password satu-satu)
     */
    protected $signature = 'make:admin
                            {--name= : Nama admin}
                            {--email= : Email admin}
                            {--password= : Password admin (min 8 karakter)}';

    protected $description = 'Buat akun admin baru (interaktif kalau opsi tidak diisi)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nama admin');
        $email = $this->option('email') ?: $this->ask('Email admin');
        $password = $this->option('password') ?: $this->secret('Password admin (min 8 karakter)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($password),
            'role'              => 'admin',
            'status'            => 'active',
            // Dibuat lewat CLI trusted di server — tidak lewat alur kode
            // verifikasi email publik, jadi langsung ditandai terverifikasi.
            'email_verified_at' => now(),
        ]);

        $this->info("Akun admin berhasil dibuat: {$admin->name} <{$admin->email}>");

        return self::SUCCESS;
    }
}
