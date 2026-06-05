<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningLog;
use Illuminate\Http\Request;

class LearningLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = LearningLog::where('user_id', $request->user()->id)
                ->with('topic')
                ->latest()
                ->get();

        return response()->json($logs);
    }
}