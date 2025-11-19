<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShootingScoreController;
use App\Http\Controllers\MarkerScanController;
use App\Http\Controllers\PrizeExchangeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::resource('/Scores', App\Http\Controllers\ScoreController::class);

// Shooting Score API endpoints
// Note: These routes are automatically prefixed with /api by Laravel 11
Route::get('/shooting-scores/top5', [ShootingScoreController::class, 'top5']);
Route::post('/shooting-scores', [ShootingScoreController::class, 'store']);
Route::get('/shooting-scores', [ShootingScoreController::class, 'index']);

// AR Stamp Rally API endpoints
Route::post('/record-marker-scan', [MarkerScanController::class, 'record']);
Route::post('/check-prize-exchange', [PrizeExchangeController::class, 'checkStatus']);
Route::post('/exchange-prize', [PrizeExchangeController::class, 'exchange']);
