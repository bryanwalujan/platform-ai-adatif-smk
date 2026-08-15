<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Guard otorisasi konsisten untuk resource yang bergantung pada mata
 * pelajaran (topik, materi, kuis, diskusi, proyek PBL, dst). Dipakai di
 * controller alih-alih if-check tersebar, supaya "siapa boleh apa" terpusat
 * di satu tempat.
 *
 * Untuk Subject itu sendiri (CRUD mapel), lihat App\Policies\SubjectPolicy.
 */
class SubjectAccessService
{
    /**
     * Pastikan $user adalah guru pengampu mata pelajaran ini (atau admin).
     * Dipakai untuk aksi menulis/mengelola konten mapel.
     */
    public function assertTeaches(User $user, int $subjectId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher() && $user->subjectsTeaching()->where('subjects.id', $subjectId)->exists()) {
            return;
        }

        throw new HttpException(403, 'Anda bukan pengampu mata pelajaran ini.');
    }

    /**
     * Pastikan $user punya akses baca ke mata pelajaran ini — guru
     * pengampunya, siswa yang terdaftar, atau admin.
     */
    public function assertEnrolled(User $user, int $subjectId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher() && $user->subjectsTeaching()->where('subjects.id', $subjectId)->exists()) {
            return;
        }

        if ($user->isStudent() && $user->subjectsEnrolled()->where('subjects.id', $subjectId)->exists()) {
            return;
        }

        throw new HttpException(403, 'Anda tidak terdaftar di mata pelajaran ini.');
    }
}
