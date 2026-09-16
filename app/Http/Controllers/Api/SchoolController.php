<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->school);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $request->user()->school->update($data);

        return response()->json($request->user()->school->fresh());
    }

    public function regenerateCode(Request $request)
    {
        $school = $request->user()->school;
        $school->update(['code' => School::newCode()]);

        return response()->json($school);
    }
}
