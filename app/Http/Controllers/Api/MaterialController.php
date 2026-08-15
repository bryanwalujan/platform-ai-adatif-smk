<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    public function show(Request $request, $id)
    {
        $material = Material::with('topic:id,title,subject_id')->findOrFail($id);
        $this->access->assertEnrolled($request->user(), $material->topic->subject_id);

        return response()->json([
            'id'               => $material->id,
            'topic_id'         => $material->topic_id,
            'title'            => $material->title,
            'content'          => $material->content,
            'video_url'        => $material->video_url,
            'duration_minutes' => $material->duration_minutes,
            'order'            => $material->order,
            'topic'            => $material->topic,
            'file_name'        => $material->file_name,
            'file_url'         => $material->file_path
                                    ? url('/api/files/' . $material->file_path)
                                    : null,
        ]);
    }
}
