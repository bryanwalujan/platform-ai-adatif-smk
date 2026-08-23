<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuruLessonPlanController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    public function index(Request $request, $subjectId)
    {
        $subject = Subject::findOrFail($subjectId);
        $this->access->assertTeaches($request->user(), $subject->id);

        $plans = LessonPlan::where('subject_id', $subjectId)
            ->with('topic:id,title')
            ->orderBy('meeting_number')
            ->get();

        return view('guru.lesson-plans.index', ['subject' => $subject, 'plans' => $plans]);
    }

    public function create(Request $request, $subjectId)
    {
        $subject = Subject::findOrFail($subjectId);
        $this->access->assertTeaches($request->user(), $subject->id);

        $topics = $subject->topics()->orderBy('order')->get(['id', 'title']);

        return view('guru.lesson-plans.create', ['subject' => $subject, 'topics' => $topics]);
    }

    public function store(Request $request, $subjectId)
    {
        $this->access->assertTeaches($request->user(), $subjectId);

        $validated = $request->validate([
            'meeting_number'      => 'required|integer|min:1|unique:lesson_plans,meeting_number,NULL,id,subject_id,' . $subjectId,
            'title'               => 'required|string|max:255',
            'learning_objective'  => 'nullable|string',
            'description'         => 'nullable|string',
            'scheduled_date'      => 'nullable|date',
            'topic_id'            => 'nullable|exists:topics,id',
            'file'                => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        $data = [
            'subject_id'          => $subjectId,
            'created_by'          => $request->user()->id,
            'topic_id'            => $validated['topic_id'] ?? null,
            'meeting_number'      => $validated['meeting_number'],
            'title'               => $validated['title'],
            'learning_objective'  => $validated['learning_objective'] ?? null,
            'description'         => $validated['description'] ?? null,
            'scheduled_date'      => $validated['scheduled_date'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $data = array_merge($data, $this->storeFile($request));
        }

        LessonPlan::create($data);

        return redirect()->route('guru.lesson-plans.index', $subjectId)
            ->with('success', 'RPP berhasil dibuat.');
    }

    public function show(Request $request, $id)
    {
        $plan = LessonPlan::with('topic:id,title', 'subject:id,name')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        return view('guru.lesson-plans.show', ['plan' => $plan]);
    }

    public function edit(Request $request, $id)
    {
        $plan = LessonPlan::with('subject:id,name')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        $topics = \App\Models\Topic::where('subject_id', $plan->subject_id)->orderBy('order')->get(['id', 'title']);

        return view('guru.lesson-plans.edit', ['plan' => $plan, 'topics' => $topics]);
    }

    public function update(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        $validated = $request->validate([
            'meeting_number'      => 'required|integer|min:1|unique:lesson_plans,meeting_number,' . $plan->id . ',id,subject_id,' . $plan->subject_id,
            'title'               => 'required|string|max:255',
            'learning_objective'  => 'nullable|string',
            'description'         => 'nullable|string',
            'scheduled_date'      => 'nullable|date',
            'topic_id'            => 'nullable|exists:topics,id',
            'file'                => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        if ($request->hasFile('file')) {
            if ($plan->file_path) {
                Storage::disk('public')->delete($plan->file_path);
            }
            $validated = array_merge($validated, $this->storeFile($request));
        }

        $plan->update($validated);

        return redirect()->route('guru.lesson-plans.index', $plan->subject_id)
            ->with('success', 'RPP berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        if ($plan->file_path) {
            Storage::disk('public')->delete($plan->file_path);
        }
        $subjectId = $plan->subject_id;
        $plan->delete();

        return redirect()->route('guru.lesson-plans.index', $subjectId)
            ->with('success', 'RPP berhasil dihapus.');
    }

    public function toggleComplete(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        $plan->update(['is_completed' => ! $plan->is_completed]);

        return back()->with('success', 'Status RPP diperbarui.');
    }

    private function storeFile(Request $request): array
    {
        $file = $request->file('file');
        $path = $file->store('lesson_plans', 'public');

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
        ];
    }
}