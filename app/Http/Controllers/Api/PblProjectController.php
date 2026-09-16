<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Models\Topic;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PblProjectController extends Controller
{
    public function __construct(private SubjectAccessService $access) {}

    public function attachment(Request $request, PblProject $project, int $index)
    {
        abort_unless($project->user_id === $request->user()->id || $this->access->teaches($request->user(), $project->subject_id), 403);
        $file = ($project->attachments ?? [])[$index] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);
        $preview = preg_match('#^(image/(jpeg|png|gif|webp)|video/|audio/)#', $file['mime_type']);

        return response()->file(Storage::disk('local')->path($file['path']), [
            'Content-Type' => $file['mime_type'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $preview ? 'inline' : 'attachment', $file['name'], 'attachment'
            ),
        ]);
    }

    /**
     * GET /pbl-projects
     * Daftar proyek milik siswa yang login. Opsional ?subject_id= untuk
     * filter satu mapel; default semua mapel (sama seperti perilaku lama).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = PblProject::where('user_id', $user->id)
            ->when($request->filled('subject_id'), function ($q) use ($request, $user) {
                $subjectId = (int) $request->subject_id;
                $this->access->assertEnrolled($user, $subjectId);
                $q->where('subject_id', $subjectId);
            })
            ->with('topic:id,title')
            ->latest()
            ->get()
            ->map(fn ($p) => $this->formatProject($p));

        return response()->json($projects);
    }

    /**
     * GET /pbl-projects/{id}
     */
    public function show(Request $request, $id)
    {
        $project = PblProject::where('user_id', $request->user()->id)
            ->with('topic:id,title')
            ->findOrFail($id);

        return response()->json($this->formatProject($project));
    }

    /**
     * POST /pbl-projects
     * subject_id ditentukan dari topic_id kalau ada (topic tahu mapelnya
     * sendiri); kalau proyek tidak terikat topik (mode bebas), pakai
     * ?subject_id= eksplisit atau fallback ke mapel siswa (lihat
     * SubjectAccessService::resolveSubjectId).
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'level' => 'required|in:Dasar,Menengah,Lanjutan',
            'topic_id' => 'nullable|school_exists:topics,id',
            'subject_id' => 'nullable|school_exists:subjects,id',
            'file' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a,pdf,txt,doc,docx,ppt,pptx,zip|extensions:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a,pdf,txt,doc,docx,ppt,pptx,zip',
            'files' => 'nullable|array|max:5',
            'files.*' => 'required|file|max:51200|mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a,pdf,txt,doc,docx,ppt,pptx,zip|extensions:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a,pdf,txt,doc,docx,ppt,pptx,zip',
        ]);

        $files = $request->file('files', []);
        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }
        if (count($files) > 5 || array_sum(array_map(fn ($f) => $f->getSize(), $files)) > 100 * 1024 * 1024) {
            throw ValidationException::withMessages(['files' => 'Maksimal 5 lampiran, 50 MB per file, total 100 MB.']);
        }
        if (! trim((string) $request->description) && ! $files) {
            throw ValidationException::withMessages(['description' => 'Isi jawaban teks atau tambahkan lampiran.']);
        }

        $user = $request->user();

        if ($request->topic_id) {
            $topic = Topic::findOrFail($request->topic_id);
            $subjectId = $topic->subject_id;
            $this->access->assertEnrolled($user, $subjectId);
        } else {
            $subjectId = $this->access->resolveSubjectId($request, $user);
        }

        $stored = [];
        try {
            $project = DB::transaction(function () use ($request, $user, $subjectId, $files, &$stored) {
                foreach ($files as $file) {
                    $path = $file->store('pbl_attachments', 'local');
                    if (! $path) {
                        throw new \RuntimeException('Lampiran gagal disimpan.');
                    }
                    $stored[] = [
                        'path' => $path,
                        'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                }

                return PblProject::create([
                    'user_id' => $user->id,
                    'topic_id' => $request->topic_id,
                    'subject_id' => $subjectId,
                    'title' => $request->title,
                    'description' => $request->description,
                    'level' => $request->level,
                    'status' => 'submitted',
                    'attachments' => $stored,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(array_column($stored, 'path'));
            throw $e;
        }

        return response()->json([
            'message' => 'Proyek berhasil dikirim',
            'project' => $this->formatProject($project),
        ], 201);
    }

    /**
     * DELETE /pbl-projects/{id}
     */
    public function destroy(Request $request, $id)
    {
        $project = PblProject::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($project->status === 'graded') {
            return response()->json([
                'message' => 'Proyek yang sudah dinilai tidak dapat dihapus',
            ], 422);
        }

        if ($project->file_path) {
            Storage::disk('public')->delete($project->file_path);
        }

        $paths = array_column($project->attachments ?? [], 'path');
        $project->delete();
        Storage::disk('local')->delete($paths);

        return response()->json(['message' => 'Proyek berhasil dihapus']);
    }

    /**
     * GET /pbl-projects/rubric
     * Kirim definisi rubrik ke Flutter agar UI dinamis
     */
    public function getRubric()
    {
        return response()->json(PblProject::rubricCriteria());
    }

    /**
     * Format project untuk response
     */
    private function formatProject(PblProject $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'level' => $p->level,
            'status' => $p->status,
            'topic' => $p->topic
                                    ? ['id' => $p->topic->id, 'title' => $p->topic->title]
                                    : null,
            'attachments' => $p->formattedAttachments(),
            'rubric' => PblProject::rubricCriteria(),
            'file_name' => $p->formattedAttachments()[0]['name'] ?? null,
            'file_url' => $p->formattedAttachments()[0]['url'] ?? null,
            'score' => $p->score,
            'rubric_scores' => $p->rubric_scores,
            'rubric_feedback' => $p->rubric_feedback,
            'feedback' => $p->feedback,
            'graded_at' => $p->graded_at?->toDateString(),
            'submitted_at' => $p->created_at->toDateString(),
        ];
    }
}
