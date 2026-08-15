<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    /**
     * Lihat detail mapel — guru pengampu, siswa terdaftar, atau admin.
     */
    public function view(User $user, Subject $subject): bool
    {
        return $user->isAdmin()
            || $subject->teachers()->where('users.id', $user->id)->exists()
            || $subject->students()->where('users.id', $user->id)->exists();
    }

    /**
     * Kelola mapel (edit, kode kelas, kelola siswa) — guru pengampu atau admin.
     */
    public function manage(User $user, Subject $subject): bool
    {
        return $user->isAdmin()
            || $subject->teachers()->where('users.id', $user->id)->exists();
    }

    /**
     * Buat mapel baru — guru yang sudah di-approve, atau admin.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->status === 'active');
    }
}
