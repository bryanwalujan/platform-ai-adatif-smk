<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

/**
 * Versi web dari Api\SubjectController (bagian guru) — buat/kelola mapel,
 * regenerate kode kelas, assign/lepas siswa manual. Otorisasi lewat
 * $this->authorize() + Policy yang sama dengan Api, jadi aturannya identik.
 */
class GuruSubjectController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    public function index(Request $request)
    {
        $subjects = $request->user()->subjectsTeaching()
            ->withCount(['students', 'topics'])
            ->latest('subjects.created_at')
            ->get();

        return view('guru.subjects.index', ['subjects' => $subjects]);
    }

    public function create()
    {
        return view('guru.subjects.create');
    }

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

        return redirect()->route('guru.subjects.show', $subject->id)
            ->with('success', "Mata pelajaran \"{$subject->name}\" berhasil dibuat.");
    }

    public function show(Request $request, $id)
    {
        $subject = Subject::withCount(['students', 'topics'])->findOrFail($id);
        $this->authorize('view', $subject);

        $subject->load(['teachers:id,name,email', 'students:id,name,email']);

        return view('guru.subjects.show', ['subject' => $subject]);
    }

    public function edit(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        return view('guru.subjects.edit', ['subject' => $subject]);
    }

    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $subject->update($validated);

        return redirect()->route('guru.subjects.show', $subject->id)
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function regenerateJoinCode(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $subject->update(['join_code' => Subject::generateUniqueJoinCode()]);

        return back()->with('success', 'Kode kelas berhasil diperbarui: ' . $subject->join_code);
    }

    /**
     * GET /guru/students/search?q=... — dipanggil via fetch() dari halaman
     * detail mapel untuk cari siswa saat menambah manual. Session-based
     * (bukan Sanctum), jadi tidak bisa reuse Api\SubjectController langsung.
     */
    public function searchStudents(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $students = User::where('role', 'siswa')
            ->where('name', 'like', '%' . $query . '%')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json($students);
    }

    public function addStudent(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $validated = $request->validate([
            'user_id' => 'required_without:email|integer|exists:users,id',
            'email'   => 'required_without:user_id|email|exists:users,email',
        ]);

        $student = isset($validated['user_id'])
            ? User::find($validated['user_id'])
            : User::where('email', $validated['email'])->first();

        if (! $student->isStudent()) {
            return back()->with('error', 'User yang dipilih bukan siswa.');
        }

        if ($subject->students()->where('users.id', $student->id)->exists()) {
            return back()->with('error', 'Siswa sudah terdaftar di mata pelajaran ini.');
        }

        $subject->students()->attach($student->id, [
            'enrollment_type' => 'assigned',
            'enrolled_at'     => now(),
        ]);

        return back()->with('success', "\"{$student->name}\" berhasil ditambahkan ke mata pelajaran.");
    }

    public function removeStudent(Request $request, $id, $studentId)
    {
        $subject = Subject::findOrFail($id);
        $this->authorize('manage', $subject);

        $subject->students()->detach($studentId);

        return back()->with('success', 'Siswa berhasil dikeluarkan dari mata pelajaran.');
    }
}