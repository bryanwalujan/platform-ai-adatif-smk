<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\AdaptiveEngineService;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    protected $adaptiveService;

    public function __construct(AdaptiveEngineService $adaptiveService)
    {
        $this->adaptiveService = $adaptiveService;
    }

    // Ambil daftar kuis berdasarkan topic
    public function getByTopic($topicId)
    {
        $quizzes = Quiz::where('topic_id', $topicId)->withCount('questions')->get();
        return response()->json($quizzes);
    }

    // Ambil soal kuis
    public function getQuestions($quizId)
    {
        $quiz = Quiz::with('questions')->findOrFail($quizId);

        // Format questions agar options selalu array, bukan JSON string
        $questions = $quiz->questions->map(function ($q) {
            return [
                'id'             => $q->id,
                'question'       => $q->question,
                // decode jika masih string, langsung pakai jika sudah array
                'options'        => is_string($q->options)
                                        ? json_decode($q->options, true)
                                        : ($q->options ?? []),
                'correct_answer' => $q->correct_answer,
            ];
        });

        return response()->json([
            'quiz'      => ['id' => $quiz->id, 'title' => $quiz->title],
            'questions' => $questions,
        ]);
    }

    // Submit jawaban kuis
    public function submit(Request $request, $quizId)
    {
        $request->validate([
            'answers'            => 'required|array',
            'time_spent_minutes' => 'nullable|integer|min:0', // TAMBAH
        ]);
    
        $quiz      = Quiz::findOrFail($quizId);
        $questions = $quiz->questions;
        $user      = $request->user();
    
        if (count($questions) === 0) {
            return response()->json(['message' => 'Kuis tidak memiliki soal'], 422);
        }
    
        $correct = 0;
        foreach ($questions as $question) {
            $userAnswer = $request->answers[(string) $question->id] ?? null;
            if ($userAnswer === $question->correct_answer) $correct++;
        }
    
        $total = count($questions);
        $score = round(($correct / $total) * 100, 2);
    
        // Gunakan waktu nyata dari Flutter, fallback 0 jika tidak dikirim
        $timeSpent = $request->input('time_spent_minutes', 0);
    
        $mastery = $this->adaptiveService->updateMastery(
            $user->id,
            $quiz->topic_id,
            $score,
            $timeSpent, // TIDAK LAGI HARDCODE
        );
    
        return response()->json([
            'message'         => 'Kuis selesai',
            'score'           => $score,
            'correct_answers' => $correct,
            'total_questions' => $total,
            'mastery_level'   => $mastery->mastery_level,
        ]);
    }
}