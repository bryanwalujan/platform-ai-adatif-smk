<?php
// app/Http/Controllers/Api/DiscussionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * GET /topics/{topicId}/discussions
     */
    public function index(Request $request, $topicId)
    {
        $topic = Topic::findOrFail($topicId);
        $this->access->assertEnrolled($request->user(), $topic->subject_id);

        $discussions = Discussion::where('topic_id', $topicId)
            ->with('user:id,name,role')
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'           => $d->id,
                'title'        => $d->title,
                'body'         => $d->body,
                'type'         => $d->type,
                'is_pinned'    => $d->is_pinned,
                'is_resolved'  => $d->is_resolved,
                'replies_count' => $d->replies_count,
                'user'         => [
                    'id'   => $d->user->id,
                    'name' => $d->user->name,
                    'role' => $d->user->role,
                ],
                'created_at'   => $d->created_at->diffForHumans(),
            ]);

        return response()->json($discussions);
    }

    /**
     * POST /topics/{topicId}/discussions
     */
    public function store(Request $request, $topicId)
    {
        $topic = Topic::findOrFail($topicId);
        $this->access->assertEnrolled($request->user(), $topic->subject_id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
            'type'  => 'nullable|in:question,discussion,sharing',
        ]);

        $discussion = Discussion::create([
            'user_id'  => $request->user()->id,
            'topic_id' => $topicId,
            'title'    => $validated['title'],
            'body'     => $validated['body'],
            'type'     => $validated['type'] ?? 'discussion',
        ]);

        return response()->json([
            'message'    => 'Diskusi berhasil dibuat',
            'discussion' => $discussion->load('user:id,name,role'),
        ], 201);
    }

    /**
     * GET /discussions/{id}
     * Detail diskusi beserta semua balasan
     */
    public function show(Request $request, $id)
    {
        $discussion = Discussion::with([
            'user:id,name,role',
            'replies.user:id,name,role',
            'topic:id,subject_id',
        ])->findOrFail($id);

        $this->access->assertEnrolled($request->user(), $discussion->topic->subject_id);

        return response()->json([
            'id'           => $discussion->id,
            'title'        => $discussion->title,
            'body'         => $discussion->body,
            'type'         => $discussion->type,
            'is_pinned'    => $discussion->is_pinned,
            'is_resolved'  => $discussion->is_resolved,
            'user'         => [
                'id'   => $discussion->user->id,
                'name' => $discussion->user->name,
                'role' => $discussion->user->role,
            ],
            'created_at'   => $discussion->created_at->diffForHumans(),
            'replies'      => $discussion->replies->map(fn($r) => [
                'id'             => $r->id,
                'body'           => $r->body,
                'is_best_answer' => $r->is_best_answer,
                'user'           => [
                    'id'   => $r->user->id,
                    'name' => $r->user->name,
                    'role' => $r->user->role,
                ],
                'created_at'     => $r->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * POST /discussions/{id}/replies
     */
    public function reply(Request $request, $id)
    {
        $discussion = Discussion::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertEnrolled($request->user(), $discussion->topic->subject_id);

        $request->validate(['body' => 'required|string']);

        $reply = DiscussionReply::create([
            'discussion_id' => $id,
            'user_id'       => $request->user()->id,
            'body'          => $request->body,
        ]);

        // Increment replies_count
        $discussion->increment('replies_count');

        return response()->json([
            'message' => 'Balasan berhasil dikirim',
            'reply'   => [
                'id'             => $reply->id,
                'body'           => $reply->body,
                'is_best_answer' => false,
                'user'           => [
                    'id'   => $request->user()->id,
                    'name' => $request->user()->name,
                    'role' => $request->user()->role,
                ],
                'created_at'     => $reply->created_at->diffForHumans(),
            ],
        ], 201);
    }

    /**
     * POST /discussions/{id}/resolve
     * Tandai diskusi sebagai selesai — oleh pembuat, atau guru yang
     * mengampu mata pelajaran topik ini.
     *
     * PERBAIKAN: dulu cek-nya cuma `role === 'guru'` — guru MANAPUN di
     * sistem bisa resolve diskusi siapapun, bukan cuma guru yang
     * mengampu mapel terkait. Makin berbahaya begitu ada banyak mapel
     * dari guru berbeda-beda.
     */
    public function resolve(Request $request, $id)
    {
        $discussion = Discussion::with('topic:id,subject_id')->findOrFail($id);
        $user = $request->user();

        $isOwner   = $discussion->user_id === $user->id;
        $isTeacher = $this->access->teaches($user, $discussion->topic->subject_id);

        if (! $isOwner && ! $isTeacher) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $discussion->update(['is_resolved' => true]);

        return response()->json(['message' => 'Diskusi ditandai selesai']);
    }

    /**
     * POST /discussions/{id}/replies/{replyId}/best
     * Sama seperti resolve() — dibatasi ke pembuat diskusi atau guru
     * pengampu mapel terkait, bukan guru manapun.
     */
    public function markBestAnswer(Request $request, $id, $replyId)
    {
        $discussion = Discussion::with('topic:id,subject_id')->findOrFail($id);
        $user = $request->user();

        $isOwner   = $discussion->user_id === $user->id;
        $isTeacher = $this->access->teaches($user, $discussion->topic->subject_id);

        if (! $isOwner && ! $isTeacher) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // Reset semua best answer di diskusi ini
        DiscussionReply::where('discussion_id', $id)
            ->update(['is_best_answer' => false]);

        DiscussionReply::findOrFail($replyId)
            ->update(['is_best_answer' => true]);

        $discussion->update(['is_resolved' => true]);

        return response()->json(['message' => 'Jawaban terbaik dipilih']);
    }

    /**
     * POST /guru/discussions/{id}/pin
     * Guru pin diskusi penting — dibatasi ke guru pengampu mapel terkait
     * saja (dulu guru manapun bisa pin diskusi apapun).
     */
    public function pin(Request $request, $id)
    {
        $discussion = Discussion::with('topic:id,subject_id')->findOrFail($id);
        $this->access->assertTeaches($request->user(), $discussion->topic->subject_id);

        $discussion->update(['is_pinned' => !$discussion->is_pinned]);

        return response()->json([
            'message'   => $discussion->is_pinned ? 'Diskusi disematkan' : 'Sematan dilepas',
            'is_pinned' => $discussion->is_pinned,
        ]);
    }
}
