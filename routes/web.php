<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Passkey (WebAuthn) authentication endpoints used by the Filament login page.
Route::passkeys();

// Temporary dev route — remove before production
Route::get('/dev/er-diagram', function () {
    return view('dev.er-diagram');
});
