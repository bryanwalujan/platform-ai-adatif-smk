<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningLog;
use Illuminate\Http\Request;

class LearningLogController extends Controller
{
    /**
     * GET /learning-logs
     * Riwayat belajar siswa untuk LearningHistoryScreen.
     */
    public function index(Request $request)
    {
        $logs = LearningLog::where('user_id', $request->user()->id)
            ->with('topic:id,title')
            ->latest()
            ->get()
            ->map(fn($log) => [
                'id'                 => $log->id,
                'topic'              => [
                    'id'    => $log->topic_id,
                    'title' => $log->topic?->title ?? '-',
                ],
                'time_spent_minutes' => $log->time_spent_minutes,
                'quiz_score'         => $log->quiz_score,
                'created_at'         => $log->created_at?->toIso8601String(),
            ]);

        return response()->json($logs);
    }

    /**
     * POST /learning-logs
     * BARU: catat log saat siswa selesai membaca materi (bukan hanya kuis).
     * Dipanggil dari MaterialDetailScreen setelah siswa selesai belajar.
     *
     * Body: { topic_id, time_spent_minutes }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_id'           => 'required|exists:topics,id',
            'time_spent_minutes' => 'required|integer|min:1',
        ]);

        $log = LearningLog::create([
            'user_id'            => $request->user()->id,
            'topic_id'           => $validated['topic_id'],
            'time_spent_minutes' => $validated['time_spent_minutes'],
            'quiz_score'         => null, // null karena ini log membaca, bukan kuis
        ]);

        return response()->json([
            'message' => 'Log belajar berhasil disimpan',
            'log_id'  => $log->id,
        ], 201);
    }
}