<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ScopesToTeacherSubjects;
use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Models\StudentTopicMastery;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\AdaptiveEngineService;
use App\Services\NotificationService;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

/**
 * Versi web dari Api\TeacherController — scoping mapel sama persis lewat
 * ScopesToTeacherSubjects, jadi tidak ada celah guru lihat data mapel yang
 * bukan diampunya walau lewat browser.
 */
class GuruPanelController extends Controller
{
    use ScopesToTeacherSubjects;

    public function __construct(
        private SubjectAccessService $access,
        private AdaptiveEngineService $engine,
    ) {
    }

    public function dashboard(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        return view('guru.dashboard', [
            'totalStudents'   => User::where('role', 'siswa')
                ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
                ->count(),
            'totalProjects'   => PblProject::whereIn('subject_id', $subjectIds)->count(),
            'pendingProjects' => PblProject::whereIn('subject_id', $subjectIds)->where('status', 'submitted')->count(),
            'totalTopics'     => Topic::whereIn('subject_id', $subjectIds)->count(),
            'subjectOptions'  => $this->teacherSubjectOptions($request),
            'currentSubjectId'=> $request->subject_id,
        ]);
    }

    public function students(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $students = User::where('role', 'siswa')
            ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->withCount('pblProjects')
            ->with(['studentMasteries' => fn ($q) => $q->whereHas(
                'topic', fn ($t) => $t->whereIn('subject_id', $subjectIds)
            )])
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id'                 => $s->id,
                'name'               => $s->name,
                'email'              => $s->email,
                'pbl_projects_count' => $s->pbl_projects_count,
                'avg_mastery'        => round($s->studentMasteries->avg(fn ($m) => $this->engine->effectiveMastery($m)) ?? 0, 1),
                'topics_learned'     => $s->studentMasteries->count(),
            ]);

        return view('guru.students.index', [
            'students'         => $students,
            'search'           => $request->search,
            'subjectOptions'   => $this->teacherSubjectOptions($request),
            'currentSubjectId' => $request->subject_id,
        ]);
    }

    public function studentShow(Request $request, $studentId)
    {
        $subjectIds = $this->assertTeachesStudent($request, (int) $studentId);

        $student = User::where('role', 'siswa')->findOrFail($studentId);

        $masteries = StudentTopicMastery::where('user_id', $studentId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->get()
            ->map(fn ($m) => [
                'topic_title'   => $m->topic?->title ?? '-',
                'mastery_level' => $this->engine->effectiveMastery($m),
                'attempts'      => $m->attempts,
                'last_accessed' => $m->last_accessed instanceof \Carbon\Carbon
                                    ? $m->last_accessed->diffForHumans()
                                    : ($m->last_accessed
                                        ? \Carbon\Carbon::parse($m->last_accessed)->diffForHumans()
                                        : '-'),
            ])
            ->sortByDesc('mastery_level')
            ->values();

        $projects = PblProject::where('user_id', $studentId)
            ->whereIn('subject_id', $subjectIds)
            ->latest()
            ->get();

        return view('guru.students.show', [
            'student'        => $student,
            'averageMastery' => round($this->engine->getAverageMastery((int) $studentId, $subjectIds), 1),
            'pblLevel'       => $this->engine->getPBLLevel((int) $studentId, $subjectIds),
            'masteries'      => $masteries,
            'projects'       => $projects,
        ]);
    }

    public function notifyStudentForm(Request $request, $studentId)
    {
        $this->assertTeachesStudent($request, (int) $studentId);

        return view('guru.students.notify', [
            'student' => User::findOrFail($studentId),
        ]);
    }

    public function notifyStudent(Request $request, $studentId)
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $this->assertTeachesStudent($request, (int) $studentId);

        app(NotificationService::class)->send(
            userId:  (int) $studentId,
            title:   $validated['title'],
            message: $validated['message'],
        );

        return redirect()->route('guru.students.show', $studentId)
            ->with('success', 'Notifikasi berhasil dikirim.');
    }

    public function pendingProjects(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $projects = PblProject::whereIn('subject_id', $subjectIds)
            ->where('status', 'submitted')
            ->with('user:id,name,email', 'topic:id,title')
            ->latest()
            ->get();

        return view('guru.projects.pending', [
            'projects'         => $projects,
            'subjectOptions'   => $this->teacherSubjectOptions($request),
            'currentSubjectId' => $request->subject_id,
        ]);
    }

    public function allProjects(Request $request)
    {
        $subjectIds = $this->relevantSubjectIds($request);

        $projects = PblProject::whereIn('subject_id', $subjectIds)
            ->with('user:id,name,email', 'topic:id,title')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return view('guru.projects.index', [
            'projects'         => $projects,
            'filters'          => $request->only(['status']),
            'subjectOptions'   => $this->teacherSubjectOptions($request),
            'currentSubjectId' => $request->subject_id,
        ]);
    }

    public function gradeProjectForm(Request $request, $projectId)
    {
        $project = PblProject::with('user:id,name,email', 'topic:id,title')->findOrFail($projectId);
        $this->access->assertTeaches($request->user(), $project->subject_id);

        if ($project->status === 'graded') {
            return redirect()->route('guru.projects.index')->with('error', 'Proyek ini sudah dinilai.');
        }

        return view('guru.projects.grade', ['project' => $project]);
    }

    public function gradeProject(Request $request, $projectId)
    {
        $validated = $request->validate([
            'feedback'                  => 'required|string|max:2000',
            'rubric_scores'              => 'required|array',
            'rubric_scores.kreativitas'  => 'required|integer|min:0|max:100',
            'rubric_scores.teknis'       => 'required|integer|min:0|max:100',
            'rubric_scores.konsep'       => 'required|integer|min:0|max:100',
            'rubric_scores.presentasi'   => 'required|integer|min:0|max:100',
            'rubric_feedback'            => 'nullable|array',
        ]);

        $project = PblProject::findOrFail($projectId);
        $this->access->assertTeaches($request->user(), $project->subject_id);

        if ($project->status === 'graded') {
            return redirect()->route('guru.projects.index')->with('error', 'Proyek sudah pernah dinilai.');
        }

        $project->rubric_scores   = $validated['rubric_scores'];
        $project->rubric_feedback = $validated['rubric_feedback'] ?? null;
        $project->feedback        = $validated['feedback'];
        $project->score           = $project->calculateWeightedScore();
        $project->status          = 'graded';
        $project->graded_at       = now();
        $project->save();

        app(NotificationService::class)->send(
            userId:  $project->user_id,
            title:   '✅ Proyek PBL Sudah Dinilai',
            message: "Proyek \"{$project->title}\" mendapat nilai {$project->score}. Lihat feedback dari guru!",
        );

        return redirect()->route('guru.projects.index')->with('success', 'Penilaian berhasil disimpan.');
    }

    public function subjects(Request $request)
    {
        $subjectIds = $this->access->teacherSubjectIds($request->user());

        return view('guru.subjects.index', [
            'subjects' => Subject::whereIn('id', $subjectIds)
                ->withCount(['students', 'topics'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}