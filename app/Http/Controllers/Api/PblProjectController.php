<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PblProject;
use Illuminate\Http\Request;

class PblProjectController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'level' => 'required',
            'file' => 'nullable|file|max:10240', // max 10MB
        ]);

        $project = PblProject::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'level' => $request->level,
            'status' => 'submitted',
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('pbl_projects', 'public');
            $project->file_path = $path;
            $project->save();
        }

        return response()->json([
            'message' => 'Proyek berhasil dikirim',
            'project' => $project
        ], 201);
    }

    public function index(Request $request)
    {
        $projects = PblProject::where('user_id', $request->user()->id)->latest()->get();
        return response()->json($projects);
    }
}