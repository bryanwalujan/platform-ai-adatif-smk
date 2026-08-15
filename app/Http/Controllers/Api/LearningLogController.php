<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningLog;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class LearningLogController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $logs = LearningLog::where('user_id', $user->id)
            ->when($request->filled('subject_id'), function ($q) use ($request, $user) {
                $subjectId = (int) $request->subject_id;
                $this->access->assertEnrolled($user, $subjectId);
                $q->whereHas('topic', fn ($t) => $t->where('subject_id', $subjectId));
            })
            ->with('topic')
            ->latest()
            ->get();

        return response()->json($logs);
    }

    /**
     * POST /learning-logs
     * BARU: route ini sudah lama terdaftar tapi method-nya belum pernah
     * dibuat (dead route, akan 500 kalau dipanggil) — ditemukan &
     * dilengkapi sekalian saat mengerjakan subject scoping.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'quiz_score'         => 'nullable|numeric|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:0',
        ]);

        $user  = $request->user();
        $topic = Topic::findOrFail($validated['topic_id']);
        $this->access->assertEnrolled($user, $topic->subject_id);

        $log = LearningLog::create([
            'user_id'            => $user->id,
            'topic_id'           => $validated['topic_id'],
            'quiz_score'         => $validated['quiz_score'] ?? null,
            'time_spent_minutes' => $validated['time_spent_minutes'] ?? 0,
        ]);

        return response()->json(['message' => 'Riwayat belajar dicatat', 'log' => $log], 201);
    }
}
