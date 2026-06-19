<?php

use App\Http\Controllers\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Passkey (WebAuthn) authentication endpoints used by the Filament login page.
Route::passkeys();

// Newsletter signup (stub — stores nothing yet, just confirms the submission).
Route::post('/newsletter', function (Request $request) {
    $request->validate(['email' => ['required', 'email']]);

    return back()->with('newsletter_success', true);
})->name('newsletter.subscribe');

// Temporary dev route — remove before production
Route::get('/dev/er-diagram', function () {
    return view('dev.er-diagram');
});

// Public CMS pages. The homepage is the system page keyed "home"; every other
// published page resolves by slug. Reserved prefixes (Filament panels, Livewire,
// passkeys, storage, dev, health) are excluded from the catch-all.
Route::get('/', [PageController::class, 'show'])->name('home');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|klientska-zona|livewire|passkeys|storage|dev|up).*$')
    ->name('page.show');
