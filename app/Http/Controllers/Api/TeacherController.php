<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * Mata pelajaran mana saja yang relevan untuk request ini.
     *
     * PERBAIKAN BESAR: seluruh controller ini dulu 100% global — query
     * `User::where('role','siswa')` tanpa filter apapun, jadi guru manapun
     * lihat SEMUA siswa & proyek di seluruh sistem, bukan cuma yang ikut
     * mapelnya. Sekarang dibatasi ke mapel yang diampu guru ini.
     *
     * Kalau ?subject_id= dikirim, dipakai satu itu saja (tervalidasi guru
     * memang mengampu). Kalau tidak, dipakai SEMUA mapel yang diampu guru
     * ini — untuk guru yang cuma punya 1 mapel (kondisi guru lama pasca
     * migrasi Fase 1), hasilnya identik dengan perilaku lama yang memang
     * cuma ada 1 mapel di seluruh sistem.
     */
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

    /**
     * Pastikan $studentId benar-benar terdaftar di salah satu mapel yang
     * diampu guru ini. Dulu tidak ada pengecekan sama sekali di
     * studentProgress/studentMastery/notifyStudent — guru manapun bisa pass
     * studentId siapapun.
     */
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

    /**
     * GET /guru/dashboard
     */
    public function dashboard(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        return response()->json([
            'total_students'   => User::where('role', 'siswa')
                ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
                ->count(),
            'total_projects'   => PblProject::whereIn('subject_id', $subjectIds)->count(),
            'pending_projects' => PblProject::whereIn('subject_id', $subjectIds)->where('status', 'submitted')->count(),
            'total_topics'     => Topic::whereIn('subject_id', $subjectIds)->count(),
        ]);
    }

    /**
     * GET /guru/students
     * Hanya kirim field yang dibutuhkan + avg_mastery untuk TeacherAdaptiveScreen
     */
    public function students(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $students = User::where('role', 'siswa')
            ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
            ->withCount('pblProjects')
            ->with(['studentMasteries' => fn ($q) => $q->whereHas(
                'topic',
                fn ($t) => $t->whereIn('subject_id', $subjectIds)
            )])
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
    public function studentProgress(Request $request, $studentId)
    {
        $subjectIds = $this->assertTeachesStudent($request, (int) $studentId);

        $student = User::where('role', 'siswa')->findOrFail($studentId);

        $masteries = StudentTopicMastery::where('user_id', $studentId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
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
            ->whereIn('subject_id', $subjectIds)
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
     * GET /guru/students/{studentId}/mastery
     * BARU: route ini sudah lama terdaftar tapi method-nya belum pernah
     * dibuat (dead route) — ditemukan & dilengkapi sekalian saat mengerjakan
     * subject scoping.
     */
    public function studentMastery(Request $request, $studentId)
    {
        $subjectIds = $this->assertTeachesStudent($request, (int) $studentId);

        $masteries = StudentTopicMastery::where('user_id', $studentId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->orderByDesc('mastery_level')
            ->get()
            ->map(fn($m) => [
                'topic_id'      => $m->topic_id,
                'topic_title'   => $m->topic?->title ?? '-',
                'mastery_level' => (float) $m->mastery_level,
                'attempts'      => $m->attempts,
                'last_accessed' => $m->last_accessed?->toIso8601String(),
            ]);

        return response()->json($masteries);
    }

    /**
     * GET /guru/pending-projects
     */
    public function pendingProjects(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $projects = PblProject::whereIn('subject_id', $subjectIds)
            ->where('status', 'submitted')
            ->with('user:id,name,email', 'topic:id,title')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'description' => $p->description,
                'level'       => $p->level,
                'status'      => $p->status,
                'user'        => $p->user,
                'topic'       => $p->topic
                                    ? ['id' => $p->topic->id, 'title' => $p->topic->title]
                                    : null,
                'file_name'   => $p->file_name,
                'file_url' => $p->file_path
                ? url('/api/files/' . $p->file_path)
                : null,
                'submitted_at' => $p->created_at?->toDateString(),
            ]);

        return response()->json($projects);
    }

    /**
     * GET /guru/all-projects
     */
    public function allProjects(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $projects = PblProject::whereIn('subject_id', $subjectIds)
            ->with('user:id,name,email', 'topic:id,title')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'description' => $p->description,
                'level'       => $p->level,
                'status'      => $p->status,
                'user'        => $p->user,
                'topic'       => $p->topic
                                    ? ['id' => $p->topic->id, 'title' => $p->topic->title]
                                    : null,
                'score'       => $p->score,
                'file_name'   => $p->file_name,
                'file_url'    => $p->file_path
                                    ? url('/api/files/' . $p->file_path)
                                    : null,
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
        $this->access->assertTeaches($request->user(), $project->subject_id);

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
        app(NotificationService::class)->send(
            userId:  $project->user_id,
            title:   '✅ Proyek PBL Sudah Dinilai',
            message: "Proyek \"{$project->title}\" mendapat nilai {$project->score}. "
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

        $this->assertTeachesStudent($request, (int) $studentId);

        app(NotificationService::class)->send(
            userId:  (int) $studentId,
            title:   $request->title,
            message: $request->message,
        );

        return response()->json(['message' => 'Notifikasi berhasil dikirim']);
    }
}
