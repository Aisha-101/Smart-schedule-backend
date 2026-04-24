<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function get(Request $request, RecommendationService $service)
    {
        $data = $service->getRecommendedTimes(
            auth()->id(),
            $request->specialist_id,
            $request->date
        );

        return response()->json($data);
    }
}