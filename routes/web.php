<?php

use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/train', function () {
    return view('train');
});

Route::get('/', function () {
    return view('findsakura');
});

Route::get('/minion', function () {
    return view('findminion');
});

Route::get('/movie', function () {
    return view('movieTest');
});
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
