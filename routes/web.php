<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcomeCounter');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/movie', function () {
    return view('movieTest');
});