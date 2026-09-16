<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_school_and_admin_without_reassigning_existing_users(): void
    {
        $this->artisan('schools:create', ['name' => 'Sekolah Baru', '--code' => 'SEKOLAHBARU'])->assertSuccessful();
        $this->artisan('make:admin', ['--school' => 'SEKOLAHBARU', '--name' => 'Admin Baru', '--email' => 'adminbaru@example.test', '--password' => 'password123'])->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'adminbaru@example.test', 'school_id' => School::where('code', 'SEKOLAHBARU')->value('id'), 'role' => 'admin']);
        $this->artisan('schools:create', ['name' => 'Duplikat', '--code' => 'SEKOLAHBARU'])->assertFailed();
    }

    public function test_file_move_checks_contents_and_can_be_rerun(): void
    {
        $originalStorage = storage_path();
        $fixture = sys_get_temp_dir().'/school-files-test-'.bin2hex(random_bytes(6));
        try {
            $this->app->useStoragePath($fixture);
            File::ensureDirectoryExists($fixture.'/app/public/materials');
            File::put($fixture.'/app/public/materials/old.pdf', 'legacy file bytes');
            Storage::fake('public');
            $this->artisan('schools:secure-files')->assertSuccessful();
            $this->assertSame('legacy file bytes', Storage::disk('public')->get('materials/old.pdf'));
            $this->assertFileDoesNotExist($fixture.'/app/public/materials/old.pdf');
            $this->artisan('schools:secure-files')->assertSuccessful();
            File::put($fixture.'/app/public/materials/old.pdf', 'different source bytes');
            $this->artisan('schools:secure-files')->assertFailed();
            $this->assertFileExists($fixture.'/app/public/materials/old.pdf');
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($fixture);
        }
    }

    public function test_stale_disk_config_cannot_delete_original_files(): void
    {
        $originalStorage = storage_path();
        $fixture = sys_get_temp_dir().'/school-files-test-'.bin2hex(random_bytes(6));
        try {
            $this->app->useStoragePath($fixture);
            File::ensureDirectoryExists($fixture.'/app/public');
            File::put($fixture.'/app/public/old.pdf', 'keep me');
            config(['filesystems.disks.public.root' => $fixture.'/app/public']);
            Storage::forgetDisk('public');
            $this->artisan('schools:secure-files')->assertFailed();
            $this->assertSame('keep me', File::get($fixture.'/app/public/old.pdf'));
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($fixture);
        }
    }
}
