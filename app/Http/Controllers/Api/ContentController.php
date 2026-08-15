<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    // ==================== TOPIK ====================

    /**
     * GET /guru/content/topics (legacy, subject_id opsional/fallback)
     * GET /guru/subjects/{subjectId}/content/topics (baru, subject_id dari URL)
     */
    public function getTopics(Request $request, $subjectId = null)
    {
        $subjectId = $this->access->resolveSubjectId($request, $request->user(), $subjectId);

        return response()->json(Topic::where('subject_id', $subjectId)->orderBy('order')->get());
    }

    public function storeTopic(Request $request, $subjectId = null)
    {
        $subjectId = $this->access->resolveSubjectId($request, $request->user(), $subjectId);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $lastOrder = Topic::where('subject_id', $subjectId)->max('order') ?? 0;

        $topic = Topic::create([
            'subject_id'  => $subjectId,
            'title'       => $request->title,
            'description' => $request->description,
            'order'       => $request->order ?? $lastOrder + 1,
        ]);

        return response()->json(['message' => 'Topik berhasil dibuat', 'topic' => $topic], 201);
    }

    /**
     * PUT /guru/content/topics/{id}
     * BARU: route ini sudah lama terdaftar tapi method-nya belum pernah
     * dibuat (dead route) — ditemukan & dilengkapi sekalian saat mengerjakan
     * subject scoping.
     */
    public function updateTopic(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $topic->update($validated);

        return response()->json(['message' => 'Topik berhasil diperbarui', 'topic' => $topic->fresh()]);
    }

    /**
     * DELETE /guru/content/topics/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function destroyTopic(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $topic->delete();

        return response()->json(['message' => 'Topik berhasil dihapus']);
    }

    // ==================== MATERI ====================
    // subject selalu diturunkan dari topic_id (topik sudah tahu mapelnya sendiri).

    public function storeMaterial(Request $request)
    {
        $request->validate([
            'topic_id'         => 'required|exists:topics,id',
            'title'            => 'required|string',
            'content'          => 'required|string',
            'video_url'        => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'file'             => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        $topic = Topic::findOrFail($request->topic_id);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $data = $request->only(['topic_id', 'title', 'content', 'video_url', 'duration_minutes']);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('materials', 'public');

            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientMimeType();
        }

        $material = Material::create($data);

        return response()->json([
            'message'  => 'Materi berhasil dibuat',
            'material' => $material,
        ], 201);
    }

    /**
     * PUT /guru/content/materials/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function updateMaterial(Request $request, $id)
    {
        $material = Material::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $material->topic->subject_id);

        $validated = $request->validate([
            'title'            => 'sometimes|string',
            'content'          => 'sometimes|string',
            'video_url'        => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
        ]);

        $material->update($validated);

        return response()->json(['message' => 'Materi berhasil diperbarui', 'material' => $material->fresh()]);
    }

    /**
     * DELETE /guru/content/materials/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function destroyMaterial(Request $request, $id)
    {
        $material = Material::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $material->topic->subject_id);

        $material->delete();

        return response()->json(['message' => 'Materi berhasil dihapus']);
    }

    // ==================== KUIS ====================

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

        return response()->json($quiz, 201);
    }

    /**
     * PUT /guru/content/quizzes/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function updateQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        $validated = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'type'               => 'nullable|in:regular,pre_test,post_test',
            'passing_score'      => 'nullable|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1',
        ]);

        $quiz->update($validated);

        return response()->json(['message' => 'Kuis berhasil diperbarui', 'quiz' => $quiz->fresh()]);
    }

    /**
     * DELETE /guru/content/quizzes/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function destroyQuiz(Request $request, $id)
    {
        $quiz = Quiz::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        $quiz->delete();

        return response()->json(['message' => 'Kuis berhasil dihapus']);
    }

    // ==================== SOAL KUIS ====================

    public function getQuizzesByTopic(Request $request, $topicId)
    {
        $topic = Topic::findOrFail($topicId);
        $this->access->assertTeaches($request->user(), $topic->subject_id);

        $quizzes = Quiz::where('topic_id', $topicId)->withCount('questions')->get();

        return response()->json($quizzes);
    }

    public function storeQuizQuestion(Request $request, $quizId)
    {
        $quiz = Quiz::with('topic:id,subject_id')->findOrFail($quizId);
        $this->access->assertTeaches($request->user(), $quiz->topic->subject_id);

        $request->validate([
            'question'       => 'required|string',
            'options'        => 'required|array|min:2',
            'correct_answer' => 'required|string',
            'explanation'    => 'nullable|string',
        ]);

        $question = QuizQuestion::create([
            'quiz_id'        => $quizId,
            'question'       => $request->question,
            'options'        => $request->options,
            'correct_answer' => $request->correct_answer,
            'explanation'    => $request->explanation,
        ]);

        return response()->json(['message' => 'Soal berhasil ditambahkan', 'question' => $question], 201);
    }

    /**
     * PUT /guru/content/questions/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function updateQuestion(Request $request, $id)
    {
        $question = QuizQuestion::with('quiz.topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $question->quiz->topic->subject_id);

        $validated = $request->validate([
            'question'       => 'sometimes|string',
            'options'        => 'sometimes|array|min:2',
            'correct_answer' => 'sometimes|string',
            'explanation'    => 'nullable|string',
        ]);

        $question->update($validated);

        return response()->json(['message' => 'Soal berhasil diperbarui', 'question' => $question->fresh()]);
    }

    /**
     * DELETE /guru/content/questions/{id}
     * BARU (lihat catatan updateTopic()).
     */
    public function destroyQuestion(Request $request, $id)
    {
        $question = QuizQuestion::with('quiz.topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $question->quiz->topic->subject_id);

        $question->delete();

        return response()->json(['message' => 'Soal berhasil dihapus']);
    }
}
