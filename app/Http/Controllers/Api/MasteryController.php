<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\AdaptiveEngineService;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class MasteryController extends Controller
{
    public function __construct(
        private AdaptiveEngineService $engine,
        private SubjectAccessService $access,
    ) {
    }

    /**
     * GET /mastery
     * Klien LAMA (tanpa ?subject_id=) tetap dapat mastery lintas SEMUA mapel
     * siswa (union) — identik dengan perilaku lama untuk siswa yang cuma
     * ikut 1 mapel (kondisi semua siswa pasca migrasi Fase 1).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $subjectIds = $request->filled('subject_id')
            ? [$this->validatedSubjectId($request, $user)]
            : $this->access->studentSubjectIds($user);

        // PERBAIKAN: 'mastery_level' yang ditampilkan sekarang nilai EFEKTIF
        // (kena decay kalau lama tidak disentuh), bukan mastery_level mentah —
        // supaya angka yang dilihat siswa/guru konsisten dengan yang dipakai
        // rekomendasi & level PBL. orderBy dipindah ke PHP karena decay
        // dihitung saat baca, tidak ada di kolom database.
        $masteries = StudentTopicMastery::where('user_id', $user->id)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->get()
            ->map(fn($m) => [
                'topic_id'      => $m->topic_id,
                'topic_title'   => $m->topic?->title ?? 'Topik tidak ditemukan',
                'mastery_level' => $this->engine->effectiveMastery($m),
                'attempts'      => $m->attempts,
                'last_accessed' => $m->last_accessed?->toIso8601String(),
            ])
            ->sortByDesc('mastery_level')
            ->values();

        return response()->json($masteries);
    }

    /**
     * POST /mastery/update
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'quiz_score'         => 'required|numeric|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:0',
        ]);

        $topic = Topic::findOrFail($validated['topic_id']);
        $this->access->assertEnrolled($request->user(), $topic->subject_id);

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

    private function validatedSubjectId(Request $request, $user): int
    {
        $subjectId = (int) $request->subject_id;
        $this->access->assertEnrolled($user, $subjectId);

        return $subjectId;
    }
}
