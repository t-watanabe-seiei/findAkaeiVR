<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FindHoufuScore;

class FindHoufuScoreController extends Controller
{
    /**
     * スコアを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'score' => 'required|numeric',
            'max_combo' => 'nullable|integer|min:0',
            'hits' => 'nullable|integer|min:0',
        ]);

        $score = FindHoufuScore::create([
            'name' => $validated['name'] ?? 'noName',
            'score' => $validated['score'],
            'max_combo' => $validated['max_combo'] ?? 0,
            'hits' => $validated['hits'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Score saved successfully',
            'data' => $score,
        ], 201);
    }

    /**
     * 上位5件のスコアを取得
     */
    public function top5()
    {
        $scores = FindHoufuScore::orderBy('score', 'desc')
                                ->take(5)
                                ->get();

        return response()->json([
            'success' => true,
            'data' => $scores,
        ]);
    }
}