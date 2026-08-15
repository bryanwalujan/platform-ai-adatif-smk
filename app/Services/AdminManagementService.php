<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;

/**
 * Logika mutasi admin murni (tanpa concern HTTP/format response) — dipakai
 * bareng oleh Api\AdminController (JSON, dipakai Flutter) dan
 * Web\AdminPanelController (Blade, panel web sementara) supaya tidak
 * duplikasi aturan bisnis di dua tempat.
 */
class AdminManagementService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function approveTeacher(User $teacher): void
    {
        $teacher->update(['status' => 'active']);

        $this->notifications->send(
            userId:  $teacher->id,
            title:   '✅ Akun Disetujui',
            message: 'Akun guru Anda sudah disetujui admin. Selamat mengajar!',
        );
    }

    public function rejectTeacher(User $teacher): void
    {
        $teacher->update(['status' => 'rejected']);

        $this->notifications->send(
            userId:  $teacher->id,
            title:   '❌ Pendaftaran Ditolak',
            message: 'Maaf, pendaftaran akun guru Anda ditolak admin. Hubungi sekolah untuk info lebih lanjut.',
        );
    }

    public function updateUserStatus(User $user, string $status): void
    {
        $user->update(['status' => $status]);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function addTeacherToSubject(Subject $subject, string $email): array
    {
        $teacher = User::where('email', $email)->where('role', 'guru')->first();

        if (! $teacher) {
            return ['ok' => false, 'message' => 'User dengan email tersebut bukan guru'];
        }

        if ($subject->teachers()->where('users.id', $teacher->id)->exists()) {
            return ['ok' => false, 'message' => 'Guru sudah menjadi pengampu mata pelajaran ini'];
        }

        $subject->teachers()->attach($teacher->id);

        return ['ok' => true, 'message' => 'Guru berhasil ditambahkan sebagai pengampu'];
    }

    public function removeTeacherFromSubject(Subject $subject, int $userId): void
    {
        $subject->teachers()->detach($userId);
    }

    public function deactivateSubject(Subject $subject): void
    {
        $subject->update(['is_active' => false]);
    }
}
