<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class GuruContentController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    // ==================== TOPIK ====================

    public function topics(Request $request, $subjectId)
    {
        $subject = Subject::findOrFail($subjectId);
        $this->access->assertTeaches($request->user(), $subject->id);

        $topics = Topic::where('subject_id', $subjectId)
            ->withCount(['materials', 'quizzes'])
            ->orderBy('order')
            ->get();

        return view('guru.content.topics.index', ['subject' => $subject, 'topics' => $topics]);
    }

    public function createTopic(Request $request, $subjectId)
    {
        $subject = Subject::findOrFail($subjectId);
        $this->access->assertTeaches($request->user(), $subject->id);

        return view('guru.content.topics.create', ['subject' => $subject]);
    }

    public function storeTopic(Request $request, $subjectId)
    {
        $this->access->assertTeaches($request->user(), $subjectId);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $lastOrder = Topic::where('subject_id', $subjectId)->max('order') ?? 0;

        Topic::create([
            'subject_id'  => $subjectId,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order'       => $validated['order'] ?? $lastOrder + 1,
        ]);

        return redirect()->route('guru.subjects.content.topics', $subjectId)->with('success', 'Topik berhasil dibuat.');
    }

    public function showTopic(Request $request, $topicId)
    {
        $topic = Topic::with('subject:id,name')
            ->withCount(['materials', 'quizzes'])
            ->findOrFail($topicId);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $materials = Material::where('topic_id', $topicId)->orderBy('order')->get();
        $quizzes   = Quiz::where('topic_id', $topicId)->withCount('questions')->get();

        return view('guru.content.topics.show', compact('topic', 'materials', 'quizzes'));
    }

    public function editTopic(Request $request, $id)
    {
        $topic = Topic::with('subject:id,name')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        return view('guru.content.topics.edit', ['topic' => $topic]);
    }

    public function updateTopic(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $topic->update($validated);

        return redirect()->route('guru.subjects.content.topics', $topic->subject_id)->with('success', 'Topik berhasil diperbarui.');
    }

    public function destroyTopic(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $subjectId = $topic->subject_id;
        $topic->delete();

        return redirect()->route('guru.subjects.content.topics', $subjectId)->with('success', 'Topik berhasil dihapus.');
    }

    // ==================== MATERI ====================

    public function createMaterial(Request $request, $topicId)
    {
        $topic = Topic::with('subject:id,name')->findOrFail($topicId);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        return view('guru.content.materials.create', ['topic' => $topic]);
    }

    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'topic_id'         => 'required|exists:topics,id',
            'title'            => 'required|string',
            'content'          => 'required|string',
            'video_url'        => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'file'             => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        $topic = Topic::findOrFail($validated['topic_id']);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $data = collect($validated)->except('file')->toArray();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->store('materials', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientMimeType();
        }

        Material::create($data);

        return redirect()->route('guru.content.topics.show', $topic->id)->with('success', 'Materi berhasil dibuat.');
    }

    public function editMaterial(Request $request, $id)
    {
        $material = Material::with('topic.subject:id,name')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $material->topic->subject_id);

        return view('guru.content.materials.edit', ['material' => $material]);
    }

    public function updateMaterial(Request $request, $id)
    {
        $material = Material::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $material->topic->subject_id);

        $validated = $request->validate([
            'title'            => 'required|string',
            'content'          => 'required|string',
            'video_url'        => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
        ]);

        $material->update($validated);

        return redirect()->route('guru.content.topics.show', $material->topic_id)->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroyMaterial(Request $request, $id)
    {
        $material = Material::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $material->topic->subject_id);

        $topicId = $material->topic_id;
        $material->delete();

        return redirect()->route('guru.content.topics.show', $topicId)->with('success', 'Materi berhasil dihapus.');
    }

    // ==================== KUIS ====================

    public function createQuiz(Request $request, $topicId)
    {
        $topic = Topic::with('subject:id,name')->findOrFail($topicId);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        return view('guru.content.quizzes.create', ['topic' => $topic]);
    }

    public function storeQuiz(Request $request)
    {
        $validated = $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'title'              => 'required|string|max:255',
            'type'               => 'nullable|in:regular,pre_test,post_test',
            'passing_score'      => 'nullable|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1',
        ]);

        $topic = Topic::findOrFail($validated['topic_id']);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $quiz = Quiz::create([
            'topic_id'           => $validated['topic_id'],
            'title'              => $validated['title'],
            'type'               => $validated['type'] ?? 'regular',
            'passing_score'      => $validated['passing_score'] ?? 70,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? 30,
        ]);

        return redirect()->route('guru.content.quizzes.show', $quiz->id)->with('success', 'Kuis berhasil dibuat.');
    }

    public function showQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic.subject:id,name', 'questions')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        return view('guru.content.quizzes.show', ['quiz' => $quiz]);
    }

    public function editQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic.subject:id,name')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        return view('guru.content.quizzes.edit', ['quiz' => $quiz]);
    }

    public function updateQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'type'               => 'nullable|in:regular,pre_test,post_test',
            'passing_score'      => 'nullable|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1',
        ]);

        $quiz->update($validated);

        return redirect()->route('guru.content.quizzes.show', $quiz->id)->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroyQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        $topicId = $quiz->topic_id;
        $quiz->delete();

        return redirect()->route('guru.content.topics.show', $topicId)->with('success', 'Kuis berhasil dihapus.');
    }

    // ==================== SOAL KUIS ====================

    public function createQuestion(Request $request, $quizId)
    {
        $quiz = Quiz::with('topic.subject:id,name')->findOrFail($quizId);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        return view('guru.content.questions.create', ['quiz' => $quiz]);
    }

    public function storeQuestion(Request $request, $quizId)
{
    $quiz = Quiz::with('topic:id,subject_id')->findOrFail($quizId);
    $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

    $validated = $request->validate([
        'question'       => 'required|string',
        'options'        => 'required|array',
        'options.*'      => 'nullable|string',
        'correct_answer' => 'required|string',
        'explanation'    => 'nullable|string',
    ]);

    // Form punya 4 slot opsi tetap tapi cuma 2 yang wajib diisi — buang slot
    // kosong dulu sebelum disimpan, supaya tidak ada opsi "" ikut tersimpan
    // dan supaya cocok dengan validasi Api\ContentController (min:2 opsi
    // TERISI, bukan min:2 SLOT).
    $options = $this->cleanOptions($validated['options']);

    if (count($options) < 2) {
        return back()
            ->withErrors(['options' => 'Minimal 2 pilihan jawaban harus diisi.'])
            ->withInput();
    }

    if (! in_array($validated['correct_answer'], $options, true)) {
        return back()
            ->withErrors(['correct_answer' => 'Jawaban benar harus salah satu dari pilihan yang diisi.'])
            ->withInput();
    }

    QuizQuestion::create([
        'quiz_id'        => $quizId,
        'question'       => $validated['question'],
        'options'        => $options,
        'correct_answer' => $validated['correct_answer'],
        'explanation'    => $validated['explanation'] ?? null,
    ]);

    return redirect()->route('guru.content.quizzes.show', $quizId)->with('success', 'Soal berhasil ditambahkan.');
}

public function updateQuestion(Request $request, $id)
{
    $question = QuizQuestion::with('quiz.topic:id,subject_id')->findOrFail($id);
    $this->access->assertTeaches($request->user(), $question->quiz->topic->subject_id);

    $validated = $request->validate([
        'question'       => 'required|string',
        'options'        => 'required|array',
        'options.*'      => 'nullable|string',
        'correct_answer' => 'required|string',
        'explanation'    => 'nullable|string',
    ]);

    $options = $this->cleanOptions($validated['options']);

    if (count($options) < 2) {
        return back()
            ->withErrors(['options' => 'Minimal 2 pilihan jawaban harus diisi.'])
            ->withInput();
    }

    if (! in_array($validated['correct_answer'], $options, true)) {
        return back()
            ->withErrors(['correct_answer' => 'Jawaban benar harus salah satu dari pilihan yang diisi.'])
            ->withInput();
    }

    $question->update([
        'question'       => $validated['question'],
        'options'        => $options,
        'correct_answer' => $validated['correct_answer'],
        'explanation'    => $validated['explanation'] ?? null,
    ]);

    return redirect()->route('guru.content.quizzes.show', $question->quiz_id)->with('success', 'Soal berhasil diperbarui.');
}

/**
 * Buang slot opsi kosong/whitespace dan re-index array — form Blade selalu
 * kirim 4 slot options[] meski cuma 2 yang wajib diisi guru.
 */
private function cleanOptions(array $rawOptions): array
{
    return array_values(array_filter(
        array_map('trim', $rawOptions),
        fn ($o) => $o !== ''
    ));
}
}