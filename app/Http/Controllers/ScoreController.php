<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\Score;


class ScoreController extends Controller
{
    public function index()     //TOP 5
    {
        return Score::orderBy('time', 'asc')
               ->take(5)
               ->get();
    }
    
    public function store(Request $request)
    {
        Score::create($request->all());
    }
    
    public function show(string $userid)
    {
        return Score::where('userid', $userid)->get();
    }


    public function update(Request $request, string $id)
    {
        Score::where('id', $id)->update([
            'userid' => $request->userid,
            'time' => $request->time
        ]);
    }

    public function destroy(string $id)
    {
        Score::where('id', $id)->delete();
    }
    
    // 番号ゲーム用: スコアを保存
    public function saveNumberGameScore(Request $request)
    {
        $validated = $request->validate([
            'time' => 'required|numeric|min:0'
        ]);
        
        $score = Score::create([
            'userid' => $request->ip(), // IPアドレスをユーザーIDとして使用
            'time' => $validated['time']
        ]);
        
        return response()->json([
            'success' => true,
            'score_id' => $score->id,
            'message' => 'Score saved successfully'
        ]);
    }
    
    // 番号ゲーム用: TOP 10ランキングを取得
    public function getNumberGameRanking()
    {
        $ranking = Score::orderBy('time', 'asc')
                       ->take(10)
                       ->get();
        
        return response()->json([
            'success' => true,
            'ranking' => $ranking
        ]);
    }
