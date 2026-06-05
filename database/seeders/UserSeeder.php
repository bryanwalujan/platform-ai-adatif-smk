<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Disable foreign key check dulu sebelum truncate
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        User::create([
            'name' => 'Andi Pratama',
            'email' => 'andi@student.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
        ]);

        // User Guru
        User::create([
            'name' => 'Bu Rina Guru',
            'email' => 'rina@guru.com',
            'password' => Hash::make('password123'),
            'role' => 'guru',
        ]);

        User::create([
            'name' => 'Pak Budi Guru',
            'email' => 'budi@guru.com',
            'password' => Hash::make('password123'),
            'role' => 'guru',
        ]);

        $this->command->info('✅ 4 User berhasil dibuat (2 Siswa + 2 Guru)');
    }
}