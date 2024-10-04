<?php

use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/', function () {
    return view('findminion');
});

Route::get('/movie', function () {
    return view('movieTest');
});
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
