<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;

class MaterialController extends Controller
{
    public function show($id)
    {
        $material = Material::with('topic')->findOrFail($id);
        return response()->json($material);
    }
}