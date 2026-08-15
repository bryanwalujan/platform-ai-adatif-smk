<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdminManagementService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private AdminManagementService $admin)
    {
    }

    /**
     * GET /admin/dashboard
     */
    public function dashboard()
    {
        return response()->json([
            'total_siswa'     => User::where('role', 'siswa')->count(),
            'total_guru'      => User::where('role', 'guru')->count(),
            'guru_pending'    => User::where('role', 'guru')->where('status', 'pending')->count(),
            'total_subjects'  => Subject::count(),
            'subjects_active' => Subject::where('is_active', true)->count(),
        ]);
    }

    // ==================== APPROVAL GURU ====================

    /**
     * GET /admin/teachers/pending
     */
    public function pendingTeachers()
    {
        $teachers = User::where('role', 'guru')
            ->where('status', 'pending')
            ->latest()
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json($teachers);
    }

    /**
     * POST /admin/teachers/{id}/approve
     */
    public function approveTeacher($id)
    {
        $teacher = User::where('role', 'guru')->findOrFail($id);
        $this->admin->approveTeacher($teacher);

        return response()->json(['message' => 'Akun guru berhasil disetujui', 'user' => $teacher->fresh()]);
    }

    /**
     * POST /admin/teachers/{id}/reject
     */
    public function rejectTeacher($id)
    {
        $teacher = User::where('role', 'guru')->findOrFail($id);
        $this->admin->rejectTeacher($teacher);

        return response()->json(['message' => 'Akun guru berhasil ditolak', 'user' => $teacher->fresh()]);
    }

    // ==================== KELOLA USER ====================

    /**
     * GET /admin/users
     */
    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(20, ['id', 'name', 'email', 'role', 'status', 'created_at']);

        return response()->json($users);
    }

    /**
     * GET /admin/users/{id}
     */
    public function userDetail($id)
    {
        $user = User::with(['subjectsTeaching:id,name', 'subjectsEnrolled:id,name'])->findOrFail($id);

        return response()->json($user);
    }

    /**
     * PUT /admin/users/{id}/status
     * Toggle generik status akun (aktifkan/nonaktifkan/tolak).
     */
    public function updateUserStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,pending,rejected',
        ]);

        $user = User::findOrFail($id);
        $this->admin->updateUserStatus($user, $validated['status']);

        return response()->json(['message' => 'Status akun berhasil diperbarui', 'user' => $user->fresh()]);
    }

    // ==================== KELOLA MATA PELAJARAN ====================

    /**
     * GET /admin/subjects
     */
    public function subjects()
    {
        $subjects = Subject::withCount(['teachers', 'students', 'topics'])
            ->latest()
            ->get();

        return response()->json($subjects);
    }

    /**
     * GET /admin/subjects/{id}
     */
    public function subjectDetail($id)
    {
        $subject = Subject::with(['teachers:id,name,email', 'createdBy:id,name'])
            ->withCount(['students', 'topics'])
            ->findOrFail($id);

        return response()->json($subject);
    }

    /**
     * POST /admin/subjects/{id}/teachers
     * Admin override: tambahkan guru sebagai pengampu mapel tertentu.
     */
    public function addTeacher(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $result = $this->admin->addTeacherToSubject($subject, $validated['email']);

        return response()->json(['message' => $result['message']], $result['ok'] ? 201 : 422);
    }

    /**
     * DELETE /admin/subjects/{id}/teachers/{userId}
     */
    public function removeTeacher($id, $userId)
    {
        $subject = Subject::findOrFail($id);
        $this->admin->removeTeacherFromSubject($subject, (int) $userId);

        return response()->json(['message' => 'Guru berhasil dilepas dari mata pelajaran']);
    }

    /**
     * DELETE /admin/subjects/{id}
     * Soft-disable, bukan hard delete — mapel yang sudah punya topik/siswa
     * live tidak boleh dihapus permanen begitu saja.
     */
    public function deactivateSubject($id)
    {
        $subject = Subject::findOrFail($id);
        $this->admin->deactivateSubject($subject);

        return response()->json(['message' => "Mata pelajaran \"{$subject->name}\" berhasil dinonaktifkan"]);
    }
}
