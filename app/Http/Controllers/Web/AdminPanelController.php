<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdminManagementService;
use Illuminate\Http\Request;

/**
 * Panel web admin sementara (Blade, session-based) — dipakai sambil
 * tampilan Flutter untuk admin belum dibangun. Logika mutasi dipakai
 * bareng dengan Api\AdminController lewat AdminManagementService, supaya
 * tidak ada aturan bisnis yang didefinisikan dua kali.
 */
class AdminPanelController extends Controller
{
    public function __construct(private AdminManagementService $admin)
    {
    }

    public function dashboard()
    {
        return view('admin.dashboard', [
            'totalSiswa'     => User::where('role', 'siswa')->count(),
            'totalGuru'      => User::where('role', 'guru')->count(),
            'guruPending'    => User::where('role', 'guru')->where('status', 'pending')->count(),
            'totalSubjects'  => Subject::count(),
            'subjectsActive' => Subject::where('is_active', true)->count(),
        ]);
    }

    // ==================== APPROVAL GURU ====================

    public function pendingTeachers()
    {
        return view('admin.teachers.pending', [
            'teachers' => User::where('role', 'guru')->where('status', 'pending')->latest()->get(),
        ]);
    }

    public function approveTeacher($id)
    {
        $teacher = User::where('role', 'guru')->findOrFail($id);
        $this->admin->approveTeacher($teacher);

        return back()->with('success', "Akun guru \"{$teacher->name}\" berhasil disetujui.");
    }

    public function rejectTeacher($id)
    {
        $teacher = User::where('role', 'guru')->findOrFail($id);
        $this->admin->rejectTeacher($teacher);

        return back()->with('success', "Akun guru \"{$teacher->name}\" berhasil ditolak.");
    }

    // ==================== KELOLA USER ====================

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
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users'   => $users,
            'filters' => $request->only(['role', 'status', 'search']),
        ]);
    }

    // ==================== KELOLA MATA PELAJARAN ====================

    public function subjects()
    {
        return view('admin.subjects.index', [
            'subjects' => Subject::withCount(['teachers', 'students', 'topics'])->latest()->get(),
        ]);
    }

    public function subjectDetail($id)
    {
        $subject = Subject::with(['teachers:id,name,email', 'students:id,name,email', 'createdBy:id,name'])
            ->withCount('topics')
            ->findOrFail($id);

        return view('admin.subjects.show', ['subject' => $subject]);
    }

    public function addTeacher(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $result = $this->admin->addTeacherToSubject($subject, $validated['email']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function removeTeacher($id, $userId)
    {
        $subject = Subject::findOrFail($id);
        $this->admin->removeTeacherFromSubject($subject, (int) $userId);

        return back()->with('success', 'Guru berhasil dilepas dari mata pelajaran.');
    }

    public function deactivateSubject($id)
    {
        $subject = Subject::findOrFail($id);
        $this->admin->deactivateSubject($subject);

        return redirect()->route('admin.subjects.index')
            ->with('success', "Mata pelajaran \"{$subject->name}\" berhasil dinonaktifkan.");
    }
}
