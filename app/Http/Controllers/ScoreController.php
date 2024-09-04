<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\Score;


class ScoreController extends Controller
{
    public function index()
    {
        return Score::all();
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
}
