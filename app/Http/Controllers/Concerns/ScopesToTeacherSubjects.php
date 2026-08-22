<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Dipakai bareng Api\TeacherController & Web\GuruPanelController supaya
 * aturan "guru cuma boleh lihat mapel yang diampunya" tidak didefinisikan
 * dua kali di dua tempat (sama seperti AdminManagementService dipakai
 * bareng Api\AdminController & Web\AdminPanelController).
 *
 * Class pemakai trait ini WAJIB inject `private SubjectAccessService $access`
 * lewat constructor — trait method di PHP tetap bisa akses property privat
 * milik class yang memakainya.
 */
trait ScopesToTeacherSubjects
{
    private function relevantSubjectIds(Request $request): array
    {
        $user = $request->user();

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->subject_id;
            $this->access->assertTeaches($user, $subjectId);

            return [$subjectId];
        }

        return $this->access->teacherSubjectIds($user);
    }

    private function assertTeachesStudent(Request $request, int $studentId): array
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $isTaught = User::where('id', $studentId)
            ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
            ->exists();

        if (! $isTaught) {
            abort(403, 'Siswa ini tidak terdaftar di mata pelajaran yang Anda ampu.');
        }

        return $subjectIds;
    }

    /** Dipakai untuk dropdown pemilih mapel di layout guru (kondisi co-teaching). */
    private function teacherSubjectOptions(Request $request)
    {
        return Subject::whereIn('id', $this->access->teacherSubjectIds($request->user()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}