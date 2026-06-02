<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary dev route — remove before production
Route::get('/dev/er-diagram', function () {
    return view('dev.er-diagram');
});
