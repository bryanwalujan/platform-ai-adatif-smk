<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * GET /topics
     * Klien LAMA (belum kirim ?subject_id=) dapat topik dari SEMUA mata
     * pelajaran user (union) — untuk user yang cuma punya 1 mapel (kondisi
     * semua user pasca migrasi Fase 1), ini identik dengan perilaku lama.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->subject_id;
            $this->access->assertEnrolled($user, $subjectId);
            $subjectIds = [$subjectId];
        } else {
            $subjectIds = $user->isAdmin()
                ? null // null = tanpa filter, admin lihat semua
                : ($user->isTeacher() ? $this->access->teacherSubjectIds($user) : $this->access->studentSubjectIds($user));
        }

        $topics = Topic::withCount('materials')
            ->when($subjectIds !== null, fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->orderBy('order')
            ->get();

        return response()->json($topics);
    }

    public function show(Request $request, $id)
    {
        $topic = Topic::with(['materials', 'quizzes'])->findOrFail($id);
        $this->access->assertEnrolled($request->user(), $topic->subject_id);

        return response()->json($topic);
    }
}
