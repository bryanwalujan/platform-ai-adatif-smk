<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
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
     * Versi non-throwing dari assertTeaches() — dipakai untuk pola
     * "pemilik ATAU guru pengampu" (mis. moderasi diskusi) yang butuh
     * boolean, bukan langsung 403.
     */
    public function teaches(User $user, int $subjectId): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->subjectsTeaching()->where('subjects.id', $subjectId)->exists());
    }

    /**
     * Versi non-throwing dari assertEnrolled().
     */
    public function enrolled(User $user, int $subjectId): bool
    {
        return $this->teaches($user, $subjectId)
            || ($user->isStudent() && $user->subjectsEnrolled()->where('subjects.id', $subjectId)->exists());
    }

    /**
     * Pastikan $user adalah guru pengampu mata pelajaran ini (atau admin).
     * Dipakai untuk aksi menulis/mengelola konten mapel.
     */
    public function assertTeaches(User $user, int $subjectId): void
    {
        if (! $this->teaches($user, $subjectId)) {
            throw new HttpException(403, 'Anda bukan pengampu mata pelajaran ini.');
        }
    }

    /**
     * Pastikan $user punya akses baca ke mata pelajaran ini — guru
     * pengampunya, siswa yang terdaftar, atau admin.
     */
    public function assertEnrolled(User $user, int $subjectId): void
    {
        if (! $this->enrolled($user, $subjectId)) {
            throw new HttpException(403, 'Anda tidak terdaftar di mata pelajaran ini.');
        }
    }

    /**
     * Semua id mata pelajaran yang diampu guru ini.
     */
    public function teacherSubjectIds(User $user): array
    {
        return $user->subjectsTeaching()->pluck('subjects.id')->all();
    }

    /**
     * Semua id mata pelajaran yang diikuti siswa ini.
     */
    public function studentSubjectIds(User $user): array
    {
        return $user->subjectsEnrolled()->pluck('subjects.id')->all();
    }

    /**
     * Tentukan subject_id yang dipakai untuk sebuah aksi (mis. bikin topik baru),
     * dengan strategi fallback supaya klien Flutter LAMA (yang tidak tahu konsep
     * mata pelajaran dan tidak pernah kirim subject_id) tetap berfungsi:
     *
     * - Kalau ada nilai eksplisit ($explicit, atau field 'subject_id' di request)
     *   → validasi user memang punya akses ke situ, lalu pakai itu.
     * - Kalau tidak ada sama sekali → pakai mata pelajaran TERLAMA milik user
     *   (guru: yang diampu; siswa: yang diikuti). Untuk user yang sudah ada
     *   sebelum migrasi multi-mapel, ini otomatis resolve ke mapel default
     *   "Animasi" hasil backfill Fase 1 — sama persis dengan perilaku lama.
     *
     * Melempar HttpException kalau user tidak punya mata pelajaran sama sekali
     * (guru baru yang belum bikin mapel / siswa baru yang belum join mapel).
     */
    public function resolveSubjectId(Request $request, User $user, ?int $explicit = null): int
    {
        $requested = $explicit ?? $request->input('subject_id');

        if ($requested) {
            if ($user->isTeacher()) {
                $this->assertTeaches($user, (int) $requested);
            } else {
                $this->assertEnrolled($user, (int) $requested);
            }

            return (int) $requested;
        }

        $fallback = $user->isTeacher()
            ? $user->subjectsTeaching()->oldest('subjects.created_at')->first()
            : $user->subjectsEnrolled()->oldest('subjects.created_at')->first();

        if (! $fallback) {
            throw new HttpException(422, $user->isTeacher()
                ? 'Anda belum mengampu mata pelajaran manapun. Buat mata pelajaran dulu.'
                : 'Anda belum terdaftar di mata pelajaran manapun. Gabung dulu pakai kode kelas.');
        }

        return $fallback->id;
    }
}
