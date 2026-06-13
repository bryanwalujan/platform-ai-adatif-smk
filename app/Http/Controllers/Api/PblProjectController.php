<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PblProjectController extends Controller
{
    /**
     * GET /pbl-projects
     * Daftar proyek milik siswa yang login
     */
    public function index(Request $request)
    {
        $projects = PblProject::where('user_id', $request->user()->id)
            ->with('topic:id,title')
            ->latest()
            ->get()
            ->map(fn($p) => $this->formatProject($p));

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
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'level'       => 'required|in:Dasar,Menengah,Lanjutan',
            'topic_id'    => 'nullable|exists:topics,id',
            'file'        => 'nullable|file|max:51200', // max 50MB
        ]);

        $project = PblProject::create([
            'user_id'     => $request->user()->id,
            'topic_id'    => $request->topic_id,
            'title'       => $request->title,
            'description' => $request->description,
            'level'       => $request->level,
            'status'      => 'submitted',
        ]);

        if ($request->hasFile('file')) {
            $file      = $request->file('file');
            $path      = $file->store('pbl_projects', 'public');
            $project->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
            ]);
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

        $project->delete();

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
    public function pendingProjects()
    {
        $projects = PblProject::where('status', 'submitted')
            ->with('user:id,name,email', 'topic:id,title')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'description' => $p->description,
                'level'       => $p->level,
                'status'      => $p->status,
                'user'        => $p->user,
                'topic'       => $p->topic
                                    ? ['id' => $p->topic->id, 'title' => $p->topic->title]
                                    : null,
                // TAMBAH: dua field ini yang hilang
                'file_name'   => $p->file_name,
                'file_url'    => $p->file_path
                                    ? url(Storage::url($p->file_path))
                                    : null,
                'submitted_at' => $p->created_at?->toDateString(),
            ]);

        return response()->json($projects);
    }
}