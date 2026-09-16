<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Material;
use App\Models\PblProject;
use App\Models\User;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SchoolFileController extends Controller
{
    public function show(Request $request, string $path, SubjectAccessService $access)
    {
        $resource = Material::where('file_path', $path)->first()
            ?? LessonPlan::where('file_path', $path)->first()
            ?? PblProject::where('file_path', $path)->first()
            ?? User::where('photo_path', $path)->first();
        abort_unless($resource, 404);
        if ($resource instanceof PblProject) {
            abort_unless($resource->user_id === $request->user()->id || $access->teaches($request->user(), $resource->subject_id), 403);
        } elseif (! $resource instanceof User) {
            $access->assertEnrolled($request->user(), $resource instanceof Material ? $resource->topic->subject_id : $resource->subject_id);
        }
        $root = realpath(Storage::disk('public')->path(''));
        $fullPath = realpath(Storage::disk('public')->path($path));
        abort_unless($root && $fullPath && str_starts_with($fullPath, $root.DIRECTORY_SEPARATOR) && is_file($fullPath), 404);
        $mime = mime_content_type($fullPath);
        $inline = preg_match('#^(image/(jpeg|png|gif|webp)$|audio/|video/|application/pdf$)#', $mime);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $resource->file_name ?? basename($path), 'attachment'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
