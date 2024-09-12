<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome0');
});

Route::get('/vr', function () {
    return view('findakaei');
});