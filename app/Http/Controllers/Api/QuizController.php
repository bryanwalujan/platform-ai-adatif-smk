<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TestResult;
use App\Models\Topic;
use App\Services\AdaptiveEngineService;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        private AdaptiveEngineService $adaptiveService,
        private SubjectAccessService $access,
    ) {
    }

    public function getByTopic(Request $request, $topicId)
    {
        $topic = Topic::findOrFail($topicId);
        $this->access->assertEnrolled($request->user(), $topic->subject_id);

        $quizzes = Quiz::where('topic_id', $topicId)
            ->withCount('questions')
            ->get()
            ->map(fn($q) => [
                'id'              => $q->id,
                'title'           => $q->title,
                'type'            => $q->type,
                'passing_score'   => $q->passing_score,
                'questions_count' => $q->questions_count,
            ]);

        return response()->json($quizzes);
    }

    public function getQuestions(Request $request, $quizId)
    {
        $quiz = Quiz::with(['questions', 'topic:id,subject_id'])->findOrFail($quizId);
        $this->access->assertEnrolled($request->user(), $quiz->topic->subject_id);

        // 'correct_answer' SENGAJA tidak dikirim di sini — ini dipakai untuk
        // render form kuis SEBELUM dijawab. Sebelumnya bocor ke client, siswa
        // yang cek response API bisa curang. Skoring tetap dihitung server-side
        // di submit(), Flutter tidak butuh correct_answer di titik ini.
        $questions = $quiz->questions->map(fn($q) => [
            'id'      => $q->id,
            'question' => $q->question,
            'options' => is_string($q->options)
                            ? json_decode($q->options, true)
                            : ($q->options ?? []),
            'point'   => $q->point,
        ]);

        return response()->json([
            'quiz' => [
                'id'            => $quiz->id,
                'title'         => $quiz->title,
                'type'          => $quiz->type,
                'passing_score' => $quiz->passing_score,
                'time_limit'    => $quiz->time_limit_minutes,
            ],
            'questions' => $questions,
        ]);
    }

    public function submit(Request $request, $quizId)
    {
        $request->validate([
            'answers'            => 'required|array',
            'time_spent_minutes' => 'nullable|integer|min:0',
        ]);

        $quiz      = Quiz::with(['questions', 'topic:id,subject_id'])->findOrFail($quizId);
        $questions = $quiz->questions;
        $user      = $request->user();

        $this->access->assertEnrolled($user, $quiz->topic->subject_id);

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'Kuis tidak memiliki soal'], 422);
        }

        // Hitung skor dengan bobot point per soal
        $totalPoints   = $questions->sum('point');
        $earnedPoints  = 0;
        $correctCount  = 0;

        foreach ($questions as $question) {
            $userAnswer = $request->answers[(string) $question->id] ?? null;
            if ($userAnswer === $question->correct_answer) {
                $earnedPoints += $question->point;
                $correctCount++;
            }
        }

        $score       = $totalPoints > 0
                        ? round(($earnedPoints / $totalPoints) * 100, 2)
                        : 0;
        $timeSpent   = $request->input('time_spent_minutes', 0);
        $passed      = $score >= $quiz->passing_score;

        // Simpan ke test_results jika pre/post test
        if (in_array($quiz->type, ['pre_test', 'post_test'])) {
            // Cek apakah sudah pernah mengerjakan
            $existing = TestResult::where('user_id', $user->id)
                ->where('topic_id', $quiz->topic_id)
                ->where('type', $quiz->type)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => $quiz->type === 'pre_test'
                        ? 'Kamu sudah mengerjakan pre-test topik ini'
                        : 'Kamu sudah mengerjakan post-test topik ini',
                    'score'   => $existing->score,
                ], 422);
            }

            TestResult::create([
                'user_id'            => $user->id,
                'quiz_id'            => $quiz->id,
                'topic_id'           => $quiz->topic_id,
                'type'               => $quiz->type,
                'score'              => $score,
                'correct_answers'    => $correctCount,
                'total_questions'    => $questions->count(),
                'time_spent_minutes' => $timeSpent,
            ]);
        }

        // BARU: catat SETIAP percobaan (regular/pre_test/post_test) ke log
        // terpisah — beda dari TestResult di atas yang cuma menyimpan satu
        // baris pre_test & satu post_test per topik. Log ini yang jadi
        // sumber data BayesianKnowledgeTracingService melacak urutan
        // benar/salah siswa dari waktu ke waktu (lihat catatan di migration
        // quiz_attempts). Murni tambahan — tidak mengubah respons/perilaku
        // endpoint ini sama sekali.
        QuizAttempt::create([
            'user_id'   => $user->id,
            'quiz_id'   => $quiz->id,
            'topic_id'  => $quiz->topic_id,
            'quiz_type' => $quiz->type,
            'score'     => $score,
            'passed'    => $passed,
        ]);

        // Update mastery hanya untuk kuis reguler dan post-test
        // Pre-test tidak mempengaruhi mastery (hanya mengukur kondisi awal)
        if ($quiz->type !== 'pre_test') {
            $this->adaptiveService->updateMastery(
                $user->id,
                $quiz->topic_id,
                $score,
                $timeSpent,
            );
        }

        return response()->json([
            'message'         => 'Kuis selesai',
            'quiz_type'       => $quiz->type,
            'score'           => $score,
            'correct_answers' => $correctCount,
            'total_questions' => $questions->count(),
            'passed'          => $passed,
            'passing_score'   => $quiz->passing_score,
            'mastery_updated' => $quiz->type !== 'pre_test',
        ]);
    }

    // BARU: riwayat hasil pre/post test siswa
    public function myResults(Request $request)
    {
        $results = TestResult::where('user_id', $request->user()->id)
            ->with('topic:id,title', 'quiz:id,title,type')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'topic'       => $r->topic?->title,
                'quiz_title'  => $r->quiz?->title,
                'type'        => $r->type,
                'score'       => $r->score,
                'created_at'  => $r->created_at->toDateString(),
            ]);

        return response()->json($results);
    }
}
