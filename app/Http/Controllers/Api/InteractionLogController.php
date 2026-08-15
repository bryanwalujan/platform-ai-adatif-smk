<?php
// app/Http/Controllers/Api/InteractionLogController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractionLog;
use App\Models\Topic;
use App\Models\User;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class InteractionLogController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * POST /interaction-logs
     * Dipanggil dari Flutter setiap ada interaksi penting.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_id'         => 'required|exists:topics,id',
            'material_id'      => 'nullable|exists:materials,id',
            'action'           => 'required|in:open_topic,open_material,play_video,finish_read,repeat_material',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $user  = $request->user();
        $topic = Topic::findOrFail($validated['topic_id']);
        $this->access->assertEnrolled($user, $topic->subject_id);

        $userId = $user->id;

        // Jika open_material, cek apakah sudah pernah dibuka sebelumnya
        // Jika sudah, ubah action menjadi repeat_material dan increment open_count
        if ($validated['action'] === 'open_material' && isset($validated['material_id'])) {
            $existing = InteractionLog::where('user_id', $userId)
                ->where('material_id', $validated['material_id'])
                ->where('action', 'open_material')
                ->first();

            if ($existing) {
                $existing->increment('open_count');
                $existing->update([
                    'action'           => 'repeat_material',
                    'duration_seconds' => ($existing->duration_seconds ?? 0)
                                          + ($validated['duration_seconds'] ?? 0),
                ]);
                return response()->json([
                    'message'    => 'Interaksi diperbarui',
                    'is_repeat'  => true,
                    'open_count' => $existing->open_count,
                ]);
            }
        }

        $log = InteractionLog::create([
            'user_id'          => $userId,
            'topic_id'         => $validated['topic_id'],
            'material_id'      => $validated['material_id'] ?? null,
            'action'           => $validated['action'],
            'duration_seconds' => $validated['duration_seconds'] ?? 0,
            'open_count'       => 1,
        ]);

        return response()->json([
            'message'   => 'Interaksi berhasil dicatat',
            'is_repeat' => false,
            'log_id'    => $log->id,
        ], 201);
    }

    /**
     * GET /interaction-logs/summary
     * Ringkasan pola belajar siswa — dipakai RecommendationScreen
     * dan AdaptiveEngineService.
     */
    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        $logs = InteractionLog::where('user_id', $userId)
            ->with('topic:id,title', 'material:id,title')
            ->latest()
            ->get();

        // Materi yang paling sering diulang (indikasi kesulitan)
        $repeated = $logs->where('action', 'repeat_material')
            ->sortByDesc('open_count')
            ->take(5)
            ->map(fn($l) => [
                'material_title' => $l->material?->title ?? '-',
                'topic_title'    => $l->topic?->title ?? '-',
                'open_count'     => $l->open_count,
                'total_minutes'  => round($l->duration_seconds / 60, 1),
            ])->values();

        return response()->json([
            'total_interactions'  => $logs->count(),
            'repeated_materials'  => $repeated,
            'most_active_topic'   => $logs->groupBy('topic_id')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first(),
        ]);
    }

    /**
     * GET /guru/students/{studentId}/interactions
     * Untuk guru melihat pola belajar siswa tertentu.
     *
     * PERBAIKAN: dulu TIDAK ADA pengecekan otorisasi sama sekali — guru
     * manapun bisa pass studentId siapapun dan lihat log interaksinya,
     * terlindungi cuma karena rute ini kebetulan ada di grup role:guru.
     * Sekarang wajib divalidasi siswa itu benar terdaftar di salah satu
     * mapel yang diampu guru ini.
     */
    public function studentSummary(Request $request, $studentId)
    {
        $subjectIds = $this->access->teacherSubjectIds($request->user());

        $isTaught = User::where('id', $studentId)
            ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
            ->exists();

        if (! $isTaught) {
            return response()->json(['message' => 'Siswa ini tidak terdaftar di mata pelajaran yang Anda ampu.'], 403);
        }

        $logs = InteractionLog::where('user_id', $studentId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title', 'material:id,title')
            ->latest()
            ->get();

        $repeated = $logs->where('action', 'repeat_material')
            ->sortByDesc('open_count')
            ->map(fn($l) => [
                'material_title' => $l->material?->title ?? '-',
                'topic_title'    => $l->topic?->title ?? '-',
                'open_count'     => $l->open_count,
                'total_minutes'  => round($l->duration_seconds / 60, 1),
            ])->values();

        return response()->json([
            'total_interactions' => $logs->count(),
            'repeated_materials' => $repeated,
            'detail_logs'        => $logs->take(20)->map(fn($l) => [
                'action'      => $l->action,
                'topic'       => $l->topic?->title,
                'material'    => $l->material?->title,
                'duration'    => round($l->duration_seconds / 60, 1) . ' menit',
                'open_count'  => $l->open_count,
                'created_at'  => $l->created_at->diffForHumans(),
            ]),
        ]);
    }
}
