<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    // ==================== GURU ====================

    /**
     * GET /guru/subjects
     * Daftar mapel yang diampu guru yang sedang login (co-teaching termasuk).
     */
    public function index(Request $request)
    {
        $subjects = $request->user()->subjectsTeaching()
            ->withCount(['students', 'topics'])
            ->latest('subjects.created_at')
            ->get();

        return response()->json($subjects);
    }

    /**
     * POST /guru/subjects
     * Guru membuat mapel baru — otomatis jadi co-teacher pertamanya.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Subject::class);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $subject = Subject::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'join_code'   => Subject::generateUniqueJoinCode(),
            'created_by'  => $request->user()->id,
            'is_active'   => true,
        ]);

        $subject->teachers()->attach($request->user()->id);

        return response()->json([
            'message' => 'Mata pelajaran berhasil dibuat',
            'subject' => $subject,
        ], 201);
    }

    /**
     * GET /guru/subjects/{id}
     */
    public function show(Request $request, $id)
    {
        $subject = Subject::withCount(['students', 'topics'])->findOrFail($id);
        $this->authorize('view', $subject);

        $subject->load(['teachers:id,name,email']);

        return response()->json($subject);
    }

    /**
     * PUT /guru/subjects/{id}
     */
    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $subject->update($validated);

        return response()->json([
            'message' => 'Mata pelajaran berhasil diperbarui',
            'subject' => $subject->fresh(),
        ]);
    }

    /**
     * POST /guru/subjects/{id}/join-code/regenerate
     */
    public function regenerateJoinCode(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $subject->update(['join_code' => Subject::generateUniqueJoinCode()]);

        return response()->json([
            'message'   => 'Kode kelas berhasil diperbarui',
            'join_code' => $subject->join_code,
        ]);
    }

    /**
     * GET /guru/subjects/{id}/students
     */
    public function students(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $students = $subject->students()
            ->withCount('studentMasteries')
            ->get()
            ->map(fn ($s) => [
                'id'              => $s->id,
                'name'            => $s->name,
                'email'           => $s->email,
                'enrollment_type' => $s->pivot->enrollment_type,
                'enrolled_at'     => $s->pivot->enrolled_at,
            ]);

        return response()->json($students);
    }

    /**
     * POST /guru/subjects/{id}/students
     * Assign manual siswa ke mapel berdasarkan email (tanpa perlu kode kelas).
     */
    public function addStudent(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $student = User::where('email', $validated['email'])->first();

        if (! $student->isStudent()) {
            return response()->json(['message' => 'User dengan email tersebut bukan siswa'], 422);
        }

        if ($subject->students()->where('users.id', $student->id)->exists()) {
            return response()->json(['message' => 'Siswa sudah terdaftar di mata pelajaran ini'], 422);
        }

        $subject->students()->attach($student->id, [
            'enrollment_type' => 'assigned',
            'enrolled_at'     => now(),
        ]);

        return response()->json(['message' => 'Siswa berhasil ditambahkan ke mata pelajaran'], 201);
    }

    /**
     * DELETE /guru/subjects/{id}/students/{studentId}
     */
    public function removeStudent(Request $request, $id, $studentId)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $subject->students()->detach($studentId);

        return response()->json(['message' => 'Siswa berhasil dikeluarkan dari mata pelajaran']);
    }

    // ==================== SISWA ====================

    /**
     * GET /subjects/my
     * Daftar mapel yang diikuti siswa yang sedang login.
     */
    public function mySubjects(Request $request)
    {
        $subjects = $request->user()->subjectsEnrolled()
            ->withCount('topics')
            ->with('teachers:id,name')
            ->latest('subjects.created_at')
            ->get();

        return response()->json($subjects);
    }

    /**
     * POST /subjects/join
     * Gabung ke mapel pakai kode kelas — sama untuk siswa maupun guru,
     * dibedakan lewat role user yang login:
     *   - siswa   → jadi peserta (subject_student, self_joined)
     *   - guru    → jadi co-teacher (subject_teacher), pakai kode yang sama
     *               yang dibagikan pemilik mapel ke rekan guru lain
     *
     * Ini pelengkap dari admin yang juga bisa assign guru ke mapel manapun
     * (lihat AdminController::addTeacher) — dua jalur, self-service atau
     * di-assign admin, sesuai kebutuhan.
     */
    public function join(Request $request)
    {
        $request->validate(['join_code' => 'required|string']);

        $user = $request->user();

        $subject = Subject::where('join_code', strtoupper(trim($request->join_code)))
            ->where('is_active', true)
            ->first();

        if (! $subject) {
            return response()->json(['message' => 'Kode kelas tidak valid atau mata pelajaran tidak aktif'], 404);
        }

        if ($user->isTeacher()) {
            if ($subject->teachers()->where('users.id', $user->id)->exists()) {
                return response()->json(['message' => 'Kamu sudah menjadi pengampu mata pelajaran ini'], 422);
            }

            $subject->teachers()->attach($user->id);

            return response()->json([
                'message' => "Berhasil bergabung sebagai pengampu \"{$subject->name}\"",
                'subject' => $subject,
            ], 201);
        }

        if (! $user->isStudent()) {
            return response()->json(['message' => 'Role Anda tidak dapat bergabung ke mata pelajaran'], 403);
        }

        if ($subject->students()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Kamu sudah terdaftar di mata pelajaran ini'], 422);
        }

        $subject->students()->attach($user->id, [
            'enrollment_type' => 'self_joined',
            'enrolled_at'     => now(),
        ]);

        return response()->json([
            'message' => "Berhasil bergabung ke mata pelajaran \"{$subject->name}\"",
            'subject' => $subject,
        ], 201);
    }

    /**
     * DELETE /subjects/my/{id}
     * Siswa keluar dari mapel. Hanya melepas pivot enrollment — data mastery
     * / learning log / hasil kuis siswa TIDAK dihapus (key-nya topic_id,
     * bukan pivot ini), jadi kalau join lagi nanti riwayatnya tetap ada.
     */
    public function leave(Request $request, $id)
    {
        $user = $request->user();
        $subject = Subject::findOrFail($id);

        if (! $subject->students()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Kamu tidak terdaftar di mata pelajaran ini'], 422);
        }

        $subject->students()->detach($user->id);

        return response()->json(['message' => "Berhasil keluar dari mata pelajaran \"{$subject->name}\""]);
    }
}
