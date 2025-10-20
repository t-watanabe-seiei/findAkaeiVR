<?php

use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {
    return view('welcome0');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/train', function () {
    return view('train');
});

Route::match(['get', 'head'], '/', function () {
    return view('shooting3DModel');
})->name('home.index');

Route::get('/minion', function () {
    return view('findminion');
});

Route::get('/movie', function () {
    return view('movieTest');
});

// Auth::routes(); // Commented out - laravel/ui not installed

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
