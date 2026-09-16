<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class MaterialController extends Controller
{
    public function __construct(private SubjectAccessService $access) {}

    public function show(Request $request, $id)
    {
        $material = Material::with('topic:id,title,subject_id')->findOrFail($id);
        $this->access->assertEnrolled($request->user(), $material->topic->subject_id);

        return response()->json($material);
    }

    public function attachment(Request $request, Material $material, string $attachment)
    {
        $this->access->assertEnrolled($request->user(), $material->topic->subject_id);
        $file = collect($material->media_files ?? [])->firstWhere('id', $attachment);
        abort_unless($file && Storage::disk('public')->exists($file['path']), 404);
        $mime = $file['mime_type'];
        $inline = preg_match('#^(image/(jpeg|png|gif|webp)$|audio/|video/|application/pdf$)#', $mime);

        return response()->file(Storage::disk('public')->path($file['path']), [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $file['name'], 'attachment'),
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
