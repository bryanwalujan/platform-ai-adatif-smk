<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PblProject;
use App\Models\StudentTopicMastery;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * Daftar semua siswa
     */
    public function students()
    {
        $students = User::where('role', 'siswa')
            ->withCount(['pblProjects', 'studentMasteries'])
            ->with('studentMasteries')
            ->get()
            ->map(function ($student) {
                $avgMastery = $student->studentMasteries->avg('mastery_level') ?? 0;
                $student->avg_mastery = round($avgMastery, 2);
                return $student;
            });

        return response()->json($students);
    }

    /**
     * Detail progress satu siswa
     */
    public function studentProgress($studentId)
    {
        $student = User::where('role', 'siswa')->findOrFail($studentId);

        $masteries = StudentTopicMastery::where('user_id', $studentId)
            ->with('topic')
            ->get();

        $projects = PblProject::where('user_id', $studentId)
            ->latest()
            ->get();

        $avgMastery = $masteries->avg('mastery_level') ?? 0;
        $pblLevel = $projects->where('score', '!=', null)->avg('score') ?? 0;

        return response()->json([
            'student' => $student,
            'average_mastery' => round($avgMastery, 2),
            'masteries' => $masteries,
            'pbl_projects' => $projects,
            'pbl_level' => round($pblLevel, 2),
        ]);
    }

    /**
     * Berikan nilai dan feedback proyek PBL
     */
    public function gradeProject(Request $request, $projectId)
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'required|string',
        ]);

        $project = PblProject::findOrFail($projectId);
        $project->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => 'Nilai dan feedback berhasil disimpan',
            'project' => $project
        ]);
    }

    /**
     * Daftar semua proyek yang belum dinilai
     */
    public function pendingProjects()
    {
        $projects = PblProject::where('status', 'submitted')
            ->with('user')
            ->latest()
            ->get();

        return response()->json($projects);
    }

    /**
     * Dashboard Guru (Ringkasan)
     */
    public function dashboard()
    {
        $totalStudents = User::where('role', 'siswa')->count();
        $totalProjects = PblProject::count();
        $pendingProjects = PblProject::where('status', 'submitted')->count();

        return response()->json([
            'total_students' => $totalStudents,
            'total_projects' => $totalProjects,
            'pending_projects' => $pendingProjects,
            'message' => 'Selamat datang di Dashboard Guru'
        ]);
    }
}