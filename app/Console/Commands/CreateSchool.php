<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSchool extends Command
{
    protected $signature = 'schools:create {name : Nama sekolah} {--code= : Kode pendaftaran (otomatis jika kosong)}';

    protected $description = 'Buat sekolah terpisah; lanjutkan dengan make:admin --school=KODE';

    public function handle(): int
    {
        $data = ['name' => $this->argument('name'), 'code' => strtoupper($this->option('code') ?: School::newCode())];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'code' => 'required|alpha_dash:ascii|min:6|max:32|unique:schools,code']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $school = School::create($data);
        $this->info("Sekolah: {$school->name}; kode: {$school->code}");
        $this->line('Buat admin dengan: php artisan make:admin --school='.$school->code);

        return self::SUCCESS;
    }
}
