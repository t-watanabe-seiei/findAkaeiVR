<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShootingScoreController;

Route::get('/welcome', function () {
    return view('welcome0');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/train', function () {
    return view('train');
});

Route::get('/', function () {
    // return view('findsakura');
    return view('shooting3DModel');
});

Route::get('/minion', function () {
    return view('findminion');
});

Route::get('/movie', function () {
    return view('movieTest');
});

// スコア保存API
Route::post('/api/shooting-scores', [ShootingScoreController::class, 'store']);
Route::get('/api/shooting-scores', [ShootingScoreController::class, 'index']);
Route::get('/api/shooting-scores/top5', [ShootingScoreController::class, 'top5']);

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
