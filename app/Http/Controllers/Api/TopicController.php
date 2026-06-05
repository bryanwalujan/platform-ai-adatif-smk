<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Topic::withCount('materials')->orderBy('order')->get();
        return response()->json($topics);
    }

    public function show($id)
    {
        // ✅ Load materials DAN quizzes
        $topic = Topic::with(['materials', 'quizzes'])->findOrFail($id);
        return response()->json($topic);
    }
}