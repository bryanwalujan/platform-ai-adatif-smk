<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;

class MaterialController extends Controller
{
    public function show($id)
    {
        $material = Material::with('topic:id,title')->findOrFail($id);
    
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