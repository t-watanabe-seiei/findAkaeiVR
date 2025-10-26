<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShootingScore;

class ShootingScoreController extends Controller
{
    /**
     * スコアを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'score' => 'required|numeric',
            'level' => 'nullable|integer|min:1|max:2',
        ]);
        
        $shootingScore = ShootingScore::create([
            'name' => $validated['name'] ?? 'noName',
            'score' => $validated['score'],
            'level' => $validated['level'] ?? 1,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Score saved successfully',
            'data' => $shootingScore,
        ], 201);
    }
    
    /**
     * 全スコアを取得（上位10件）
     */
    public function index()
    {
        $scores = ShootingScore::orderBy('score', 'desc')
                               ->take(10)
                               ->get();
        
        return response()->json([
            'success' => true,
            'data' => $scores,
        ]);
    }
    
    /**
     * 上位5件のスコアを取得（レベル別）
     */
    public function top5(Request $request)
    {
        $level = $request->query('level', 1); // デフォルトはレベル1
        
        $scores = ShootingScore::where('level', $level)
                               ->orderBy('score', 'desc')
                               ->take(5)
                               ->get();
        
        return response()->json([
            'success' => true,
            'data' => $scores,
        ]);
    }
}
