<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PblProject;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * GET /guru/dashboard
     */
    public function dashboard()
    {
        return response()->json([
            'total_students'   => User::where('role', 'siswa')->count(),
            'total_projects'   => PblProject::count(),
            'pending_projects' => PblProject::where('status', 'submitted')->count(),
            'total_topics'     => Topic::count(), // TAMBAH: dipakai stat card Flutter
        ]);
    }

    /**
     * GET /guru/students
     * Hanya kirim field yang dibutuhkan + avg_mastery untuk TeacherAdaptiveScreen
     */
    public function students()
    {
        $students = User::where('role', 'siswa')
            ->withCount('pblProjects')
            ->with('studentMasteries')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'email'      => $s->email,
                'avg_mastery' => round($s->studentMasteries->avg('mastery_level') ?? 0, 1),
                // dipakai StudentListScreen: jumlah topik yang sudah dipelajari
                'student_masteries_count' => $s->studentMasteries->count(),
            ]);

        return response()->json($students);
    }

    /**
     * GET /guru/students/{studentId}/progress
     * Format masteries disesuaikan dengan StudentProgressScreen Flutter
     */
    public function studentProgress($studentId)
    {
        $student = User::where('role', 'siswa')->findOrFail($studentId);

        $masteries = StudentTopicMastery::where('user_id', $studentId)
            ->with('topic:id,title')
            ->orderByDesc('mastery_level')
            ->get()
            ->map(fn($m) => [
                'topic_title'   => $m->topic?->title ?? '-', // Flutter akses m['topic_title']
                'mastery_level' => (float) $m->mastery_level,
                'attempts'      => $m->attempts,
                'last_accessed' => $m->last_accessed?->diffForHumans(),
            ]);

        $avgMastery = $masteries->avg('mastery_level') ?? 0;

        // PERBAIKAN: pbl_level dari mastery, bukan score proyek
        $pblLevel = match(true) {
            $avgMastery >= 85 => 'Lanjutan',
            $avgMastery >= 65 => 'Menengah',
            default           => 'Dasar',
        };

        $projects = PblProject::where('user_id', $studentId)
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'description' => $p->description,
                'level'       => $p->level,
                'status'      => $p->status,
                'score'       => $p->score,
                'feedback'    => $p->feedback,
                'submitted_at' => $p->created_at?->toDateString(),
            ]);

        return response()->json([
            'student'         => ['id' => $student->id, 'name' => $student->name],
            'average_mastery' => round($avgMastery, 1),
            'pbl_level'       => $pblLevel,
            'masteries'       => $masteries,
            'pbl_projects'    => $projects,
        ]);
    }

    /**
     * GET /guru/pending-projects
     */
    public function pendingProjects()
    {
        $projects = PblProject::where('status', 'submitted')
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'description' => $p->description,
                'level'       => $p->level,
                'status'      => $p->status,
                'user'        => $p->user,
                'submitted_at' => $p->created_at?->toDateString(),
            ]);

        return response()->json($projects);
    }

    /**
     * POST /guru/projects/{projectId}/grade
     */
    public function gradeProject(Request $request, $projectId)
    {
        $request->validate([
            'score'    => 'required|integer|min:0|max:100',
            'feedback' => 'required|string|max:1000',
        ]);

        $project = PblProject::findOrFail($projectId);

        // Pastikan hanya proyek yang belum dinilai yang bisa digrade
        if ($project->status === 'graded') {
            return response()->json([
                'message' => 'Proyek ini sudah pernah dinilai',
            ], 422);
        }

        $project->update([
            'score'    => $request->score,
            'feedback' => $request->feedback,
            'status'   => 'graded',
        ]);

        return response()->json([
            'message' => 'Nilai dan feedback berhasil disimpan',
            'project' => $project,
        ]);
    }
}