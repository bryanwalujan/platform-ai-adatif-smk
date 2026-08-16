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
        // PERBAIKAN: dulu rata-rata dihitung langsung dari mastery_level mentah
        // di query ini, sementara getPBLLevel() menghitungnya sendiri dari nilai
        // efektif (kena decay) — dua angka bisa tidak konsisten satu sama lain.
        // Sekarang keduanya lewat getAverageMastery() yang sama.
        $avgMastery = $this->engine->getAverageMastery($userId, $subjectIds);

        return response()->json([
            'pbl_level'       => $pblLevel,
            'avg_mastery'     => round($avgMastery, 1),
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
            ->get();

        // PERBAIKAN: pakai mastery EFEKTIF (kena decay) konsisten di seluruh
        // laporan — dulu 'mastery_level' mentah dipakai untuk avg, urutan
        // tampilan, dan hitung completed_topics, jadi bisa tidak sinkron
        // dengan pbl_level yang sudah lebih dulu pakai nilai efektif.
        $withEffective = $masteries
            ->map(fn ($m) => [
                'topic_title'   => $m->topic?->title ?? '-',
                'mastery_level' => $this->engine->effectiveMastery($m),
                'attempts'      => $m->attempts,
            ])
            ->sortByDesc('mastery_level')
            ->values();

        $avgMastery = $this->engine->getAverageMastery($userId, $subjectIds);
        $pblLevel   = $this->engine->getPBLLevel($userId, $subjectIds);

        return response()->json([
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'pbl_level'        => $pblLevel,
            'avg_mastery'      => round($avgMastery, 1),
            'mastery_list'     => $withEffective,
            // PERBAIKAN: dulu Topic::count() global (semua mapel di seluruh
            // sistem) — sekarang dibatasi ke mapel yang relevan buat siswa ini.
            'total_topics'     => Topic::whereIn('subject_id', $subjectIds)->count(),
            'completed_topics' => $withEffective->where('mastery_level', '>=', 75)->count(),
            'generated_at'     => now()->toIso8601String(),
        ]);
    }
}
