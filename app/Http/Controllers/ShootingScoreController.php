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
            'game_mode' => 'nullable|string|max:50',
            'max_combo' => 'nullable|integer|min:0',
            'enemies_defeated' => 'nullable|integer|min:0',
        ]);
        
        $shootingScore = ShootingScore::create([
            'name' => $validated['name'] ?? 'noName',
            'score' => $validated['score'],
            'level' => $validated['level'] ?? 1,
            'game_mode' => $validated['game_mode'] ?? 'terrer',
            'max_combo' => $validated['max_combo'] ?? 0,
            'enemies_defeated' => $validated['enemies_defeated'] ?? 0,
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
     * 上位5件のスコアを取得（レベル別・ゲームモード別）
     */
    public function top5(Request $request)
    {
        $level = $request->query('level', 1); // デフォルトはレベル1
        $gameMode = $request->query('game_mode', 'terrer'); // デフォルトはterrer
        
        $scores = ShootingScore::where('level', $level)
                               ->where('game_mode', $gameMode)
                               ->orderBy('score', 'desc')
                               ->take(5)
                               ->get();
        
        return response()->json([
            'success' => true,
            'data' => $scores,
        ]);
    }
}
