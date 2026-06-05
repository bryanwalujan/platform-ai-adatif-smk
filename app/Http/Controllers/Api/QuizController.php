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
        return response()->json([
            'quiz' => $quiz,
            'questions' => $quiz->questions
        ]);
    }

    // Submit jawaban kuis
    public function submit(Request $request, $quizId)
    {
        $request->validate([
            'answers' => 'required|array'
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $questions = $quiz->questions;
        $user = $request->user();

        $correct = 0;
        $total = count($questions);

        foreach ($questions as $question) {
            $userAnswer = $request->answers[$question->id] ?? null;
            if ($userAnswer === $question->correct_answer) {
                $correct++;
            }
        }

        $score = round(($correct / $total) * 100, 2);

        // Update Mastery menggunakan Adaptive Engine
        $mastery = $this->adaptiveService->updateMastery(
            $user->id, 
            $quiz->topic_id, 
            $score,
            25 // contoh waktu pengerjaan dalam menit
        );

        // Catat learning log
        \App\Models\LearningLog::create([
            'user_id' => $user->id,
            'topic_id' => $quiz->topic_id,
            'material_id' => null,
            'time_spent_minutes' => 25,
        ]);

        return response()->json([
            'message' => 'Kuis selesai',
            'score' => $score,
            'correct_answers' => $correct,
            'total_questions' => $total,
            'mastery_level' => $mastery->mastery_level,
            'new_recommendations' => $this->adaptiveService->getRecommendations($user->id)
        ]);
    }
}