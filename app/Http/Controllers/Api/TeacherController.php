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
                'topic_title'   => $m->topic?->title ?? '-',
                'mastery_level' => (float) $m->mastery_level,
                'attempts'      => $m->attempts,
                // PERBAIKAN: pastikan tidak crash jika last_accessed null atau string
                'last_accessed' => $m->last_accessed instanceof \Carbon\Carbon
                                    ? $m->last_accessed->diffForHumans()
                                    : ($m->last_accessed
                                        ? \Carbon\Carbon::parse($m->last_accessed)->diffForHumans()
                                        : '-'),
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
            'feedback'         => 'required|string|max:2000',
            'rubric_scores'    => 'required|array',
            'rubric_scores.kreativitas'  => 'required|integer|min:0|max:100',
            'rubric_scores.teknis'       => 'required|integer|min:0|max:100',
            'rubric_scores.konsep'       => 'required|integer|min:0|max:100',
            'rubric_scores.presentasi'   => 'required|integer|min:0|max:100',
            'rubric_feedback'  => 'nullable|array',
        ]);

        $project = PblProject::findOrFail($projectId);

        if ($project->status === 'graded') {
            return response()->json(['message' => 'Proyek sudah pernah dinilai'], 422);
        }

        // Hitung skor total dengan weighted average
        $project->rubric_scores   = $request->rubric_scores;
        $project->rubric_feedback = $request->rubric_feedback;
        $project->feedback        = $request->feedback;
        $project->score           = $project->calculateWeightedScore();
        $project->status          = 'graded';
        $project->graded_at       = now();
        $project->save();

        // Kirim notifikasi ke siswa
        app(\App\Services\NotificationService::class)->sendToStudent(
            studentId: $project->user_id,
            title:     '✅ Proyek PBL Sudah Dinilai',
            message:   "Proyek \"{$project->title}\" mendapat nilai {$project->score}. "
                     . "Lihat feedback dari guru!",
        );

        return response()->json([
            'message' => 'Penilaian berhasil disimpan',
            'score'   => $project->score,
            'project' => $project,
        ]);
    }

    public function notifyStudent(Request $request, $studentId)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        app(\App\Services\NotificationService::class)->sendToStudent(
            studentId: (int) $studentId,
            title:     $request->title,
            message:   $request->message,
        );

        return response()->json(['message' => 'Notifikasi berhasil dikirim']);
    }
}