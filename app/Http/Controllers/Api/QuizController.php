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
            'answers' => 'required|array'
        ]);

        try {
            \Log::info('Quiz submit - Start', ['quiz_id' => $quizId, 'user_id' => $request->user()?->id]);

            $quiz = Quiz::findOrFail($quizId);
            $questions = $quiz->questions;
            $user = $request->user();

            $total = $questions->count();

            // Validasi: jika tidak ada soal, tolak lebih awal
            if ($total === 0) {
                \Log::warning('Quiz submit - No questions found', ['quiz_id' => $quizId]);
                return response()->json(['message' => 'Kuis tidak memiliki soal'], 422);
            }

            \Log::info('Quiz submit - Questions loaded', ['total_questions' => $total]);

            // Hitung jawaban yang benar
            $correct = 0;
            foreach ($questions as $question) {
                // Cast ke string agar cocok dengan key JSON yang selalu string
                $userAnswer = $request->answers[(string) $question->id] ?? null;
                if ($userAnswer === $question->correct_answer) {
                    $correct++;
                }
            }

            $score = round(($correct / $total) * 100, 2);
            \Log::info('Quiz submit - Score calculated', ['score' => $score, 'correct' => $correct, 'total' => $total]);

            // Update Mastery menggunakan Adaptive Engine
            \Log::info('Quiz submit - Calling updateMastery', ['user_id' => $user->id, 'topic_id' => $quiz->topic_id]);
            $startTime = microtime(true);
            
            $mastery = $this->adaptiveService->updateMastery(
                $user->id, 
                $quiz->topic_id, 
                $score,
                25 // contoh waktu pengerjaan dalam menit
            );

            $elapsed = (microtime(true) - $startTime) * 1000;
            \Log::info('Quiz submit - updateMastery completed', ['elapsed_ms' => $elapsed, 'mastery_level' => $mastery->mastery_level]);

            \Log::info('Quiz submit - Success', ['user_id' => $user->id, 'quiz_id' => $quizId]);

            return response()->json([
                'message' => 'Kuis selesai',
                'score' => $score,
                'correct_answers' => $correct,
                'total_questions' => $total,
                'mastery_level' => $mastery->mastery_level,
                'new_recommendations' => $this->adaptiveService->getRecommendations($user->id)
            ]);

        } catch (\Exception $e) {
            \Log::error('Quiz submit - Error', [
                'quiz_id' => $quizId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}