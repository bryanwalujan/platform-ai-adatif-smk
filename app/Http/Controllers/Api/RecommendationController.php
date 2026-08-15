<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\AdaptiveEngineService;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(
        private AdaptiveEngineService $engine,
        private SubjectAccessService $access,
    ) {
    }

    /**
     * Mata pelajaran mana saja yang relevan untuk request ini. Kalau
     * ?subject_id= dikirim, dipakai satu itu saja (setelah divalidasi
     * siswa memang ikut mapel itu). Kalau tidak, dipakai SEMUA mapel yang
     * diikuti siswa — untuk siswa yang cuma ikut 1 mapel (kondisi semua
     * siswa pasca migrasi Fase 1), hasilnya identik dengan perilaku lama.
     */
    private function relevantSubjectIds(Request $request): array
    {
        $user = $request->user();

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->subject_id;
            $this->access->assertEnrolled($user, $subjectId);

            return [$subjectId];
        }

        return $this->access->studentSubjectIds($user);
    }

    /**
     * GET /recommendations
     */
    public function index(Request $request)
    {
        $userId     = $request->user()->id;
        $subjectIds = $this->relevantSubjectIds($request);

        $recommendations = $this->engine->getRecommendations($userId, $subjectIds);
        $pblLevel        = $this->engine->getPBLLevel($userId, $subjectIds);
        $avgMastery       = StudentTopicMastery::where('user_id', $userId)
                            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
                            ->avg('mastery_level') ?? 0;

        return response()->json([
            'pbl_level'       => $pblLevel,
            'avg_mastery'     => round((float) $avgMastery, 1),
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * GET /progress-report
     */
    public function progressReport(Request $request)
    {
        $user       = $request->user();
        $userId     = $user->id;
        $subjectIds = $this->relevantSubjectIds($request);

        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->orderByDesc('mastery_level')
            ->get();

        $avgMastery = $masteries->avg('mastery_level') ?? 0;
        $pblLevel   = $this->engine->getPBLLevel($userId, $subjectIds);

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
            // PERBAIKAN: dulu Topic::count() global (semua mapel di seluruh
            // sistem) — sekarang dibatasi ke mapel yang relevan buat siswa ini.
            'total_topics'     => Topic::whereIn('subject_id', $subjectIds)->count(),
            'completed_topics' => $masteries->where('mastery_level', '>=', 75)->count(),
            'generated_at'     => now()->toIso8601String(),
        ]);
    }
}
