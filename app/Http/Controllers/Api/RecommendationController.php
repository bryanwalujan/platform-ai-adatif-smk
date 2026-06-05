<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\AdaptiveEngineService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected AdaptiveEngineService $engine;

    public function __construct(AdaptiveEngineService $engine)
    {
        $this->engine = $engine;
    }

    /**
     * GET /recommendations
     * Dipakai oleh RecommendationScreen Flutter.
     *
     * Response:
     * {
     *   "pbl_level": "Menengah",
     *   "avg_mastery": 68.5,
     *   "recommendations": [
     *     {
     *       "type": "review",
     *       "priority": "high",
     *       "message": "Kamu sangat perlu mengulang topik ini",
     *       "topic": { "id": 1, "title": "Prinsip Animasi" }
     *     },
     *     ...
     *   ]
     * }
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $recommendations = $this->engine->getRecommendations($userId);
        $pblLevel        = $this->engine->getPBLLevel($userId);
        $avgMastery      = StudentTopicMastery::where('user_id', $userId)
                            ->avg('mastery_level') ?? 0;

        return response()->json([
            'pbl_level'       => $pblLevel,
            'avg_mastery'     => round((float) $avgMastery, 1),
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * GET /progress-report
     * BARU: endpoint untuk ProgressReportScreen — mengembalikan semua data
     * yang dibutuhkan untuk generate PDF di Flutter.
     *
     * Response:
     * {
     *   "user": { "name", "email" },
     *   "pbl_level": "Menengah",
     *   "avg_mastery": 68.5,
     *   "mastery_list": [
     *     { "topic_title": "Prinsip Animasi", "mastery_level": 80, "attempts": 3 },
     *     ...
     *   ],
     *   "total_topics": 10,
     *   "completed_topics": 4,
     *   "generated_at": "2025-06-01T10:00:00Z"
     * }
     */
    public function progressReport(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->with('topic:id,title')
            ->orderByDesc('mastery_level')
            ->get();

        $avgMastery = $masteries->avg('mastery_level') ?? 0;
        $pblLevel   = $this->engine->getPBLLevel($userId);

        return response()->json([
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'pbl_level'        => $pblLevel,
            'avg_mastery'      => round((float) $avgMastery, 1),
            'mastery_list'     => $masteries->map(fn($m) => [
                'topic_title'   => $m->topic?->title ?? '-',
                'mastery_level' => (float) $m->mastery_level,
                'attempts'      => $m->attempts,
            ]),
            'total_topics'     => Topic::count(),
            'completed_topics' => $masteries->where('mastery_level', '>=', 75)->count(),
            'generated_at'     => now()->toIso8601String(),
        ]);
    }
}