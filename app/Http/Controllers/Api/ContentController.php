<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    // ==================== TOPIK ====================
    public function getTopics()
    {
        return response()->json(Topic::orderBy('order')->get());
    }

    public function storeTopic(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $lastOrder = Topic::max('order') ?? 0;

        $topic = Topic::create([
            'title'       => $request->title,
            'description' => $request->description,
            'order'       => $request->order ?? $lastOrder + 1,
        ]);

        return response()->json(['message' => 'Topik berhasil dibuat', 'topic' => $topic], 201);
    }

    // ==================== MATERI ====================
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

    // ==================== KUIS ====================
    public function storeQuiz(Request $request)
    {
        $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'title'              => 'required|string',
            'time_limit_minutes' => 'nullable|integer',
            'passing_score'      => 'nullable|integer',
        ]);

        $quiz = Quiz::create($request->all());

        return response()->json(['message' => 'Kuis berhasil dibuat', 'quiz' => $quiz], 201);
    }

    // ==================== SOAL KUIS ====================
    public function getQuizzesByTopic($topicId)
    {
        $quizzes = Quiz::where('topic_id', $topicId)->withCount('questions')->get();
        return response()->json($quizzes);
    }

    public function storeQuizQuestion(Request $request, $quizId)
    {
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
}