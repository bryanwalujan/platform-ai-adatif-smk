<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecureSchoolFiles extends Command
{
    protected $signature = 'schools:secure-files';

    protected $description = 'Pindahkan file publik lama ke penyimpanan privat; jalankan saat maintenance setelah migrasi';

    public function handle(): int
    {
        $source = storage_path('app/public');
        if (! is_dir($source)) {
            $this->info('Tidak ada direktori file publik lama.');

            return self::SUCCESS;
        }
        $destination = Storage::disk('public')->path('');
        if (rtrim($source, '/') === rtrim($destination, '/') || (realpath($destination) && realpath($source) === realpath($destination))) {
            $this->error('Disk masih menunjuk folder publik lama. Jalankan php artisan optimize:clear terlebih dahulu.');

            return self::FAILURE;
        }
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) {
                $this->error('Tautan simbolik di penyimpanan publik perlu diperiksa manual: '.$file->getPathname());

                return self::FAILURE;
            }
            if (! $file->isFile() || $file->getFilename() === '.gitignore') {
                continue;
            }
            $path = substr($file->getPathname(), strlen($source) + 1);
            $target = Storage::disk('public');
            if (! $target->exists($path)) {
                $stream = fopen($file->getPathname(), 'rb');
                try {
                    if (! $target->put($path, $stream)) {
                        throw new \RuntimeException('Gagal menyalin '.$path);
                    }
                } finally {
                    fclose($stream);
                }
            }
            if (hash_file('sha256', $file->getPathname()) !== hash_file('sha256', $target->path($path))) {
                $this->error('Isi file tujuan berbeda; file asli dipertahankan: '.$path);

                return self::FAILURE;
            }
            if (! unlink($file->getPathname())) {
                $this->error('Tidak bisa menghapus salinan publik: '.$path);

                return self::FAILURE;
            }
            $count++;
        }
        $this->info("{$count} file dipindahkan dan diverifikasi. Perintah aman dijalankan ulang.");

        return self::SUCCESS;
    }
}
