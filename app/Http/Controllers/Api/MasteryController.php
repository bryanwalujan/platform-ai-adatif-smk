<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Services\AdaptiveEngineService;
use Illuminate\Http\Request;

class MasteryController extends Controller
{
    protected AdaptiveEngineService $engine;

    public function __construct(AdaptiveEngineService $engine)
    {
        $this->engine = $engine;
    }

    /**
     * GET /mastery
     * Mengembalikan mastery level semua topik milik siswa yang login.
     * Struktur ini yang dibutuhkan MasteryScreen (BarChart) di Flutter.
     *
     * Response:
     * [
     *   {
     *     "topic_id": 1,
     *     "topic_title": "Prinsip Animasi",
     *     "mastery_level": 75.50,
     *     "attempts": 3,
     *     "last_accessed": "2025-06-01T10:00:00Z"
     *   },
     *   ...
     * ]
     */
    public function index(Request $request)
    {
        $masteries = StudentTopicMastery::where('user_id', $request->user()->id)
            ->with('topic:id,title')
            ->orderByDesc('mastery_level')
            ->get()
            ->map(fn($m) => [
                'topic_id'      => $m->topic_id,
                'topic_title'   => $m->topic?->title ?? 'Topik tidak ditemukan',
                'mastery_level' => (float) $m->mastery_level,
                'attempts'      => $m->attempts,
                'last_accessed' => $m->last_accessed?->toIso8601String(),
            ]);

        return response()->json($masteries);
    }

    /**
     * POST /mastery/update
     * Update mastery secara manual (opsional).
     * Biasanya dipanggil otomatis dari QuizController@submit.
     *
     * Body: { topic_id, quiz_score, time_spent_minutes }
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'quiz_score'         => 'required|numeric|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:0',
        ]);

        $mastery = $this->engine->updateMastery(
            userId:            $request->user()->id,
            topicId:           $validated['topic_id'],
            quizScore:         $validated['quiz_score'],
            timeSpentMinutes:  $validated['time_spent_minutes'] ?? 0,
        );

        return response()->json([
            'mastery_level' => $mastery->mastery_level,
            'attempts'      => $mastery->attempts,
            'message'       => 'Mastery berhasil diperbarui',
        ]);
    }
}