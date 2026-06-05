<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdaptiveEngineService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected $adaptiveService;

    public function __construct(AdaptiveEngineService $adaptiveService)
    {
        $this->adaptiveService = $adaptiveService;
    }

    public function index(Request $request)
    {
        $recommendations = $this->adaptiveService->getRecommendations($request->user()->id);
        return response()->json([
            'recommendations' => $recommendations,
            'pbl_level' => $this->adaptiveService->getPBLLevel($request->user()->id)
        ]);
    }
}