<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceCategoryController;
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

// Public service category pages (default data-driven layout, or a custom page
// attached via the polymorphic `pageable` relationship). Registered before the
// catch-all so the prefixed path resolves to its own controller.
Route::get('/sluzby/{category:slug}', [ServiceCategoryController::class, 'show'])
    ->name('service-category.show');

// Public reservation wizard. One unified component; the two SEO presets scope it
// to a service type and start in category-first order.
Route::get('/rezervace', fn () => view('reservation.index'))->name('reservation.wizard');
Route::get('/rezervace-vstupniho-vysetreni', fn () => view('reservation.index', ['preset' => 'vstupni']))->name('reservation.vstupni');
Route::get('/rezervace-masazi', fn () => view('reservation.index', ['preset' => 'masaz']))->name('reservation.masaz');

// Public login (web guard). Also the wizard's login fallback / forgotten-password entry.
Route::get('/prihlaseni', fn () => view('auth.login'))->name('public.login');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|klientska-zona|livewire|passkeys|storage|dev|up|rezervace|rezervace-vstupniho-vysetreni|rezervace-masazi|prihlaseni).*$')
    ->name('page.show');
