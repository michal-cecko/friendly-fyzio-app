<?php

use App\Http\Controllers\InstagramOAuthController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TherapistController;
use Illuminate\Support\Facades\Route;

// Passkey (WebAuthn) authentication endpoints used by the Filament login page.
Route::passkeys();

// Newsletter signup — subscribes the email to the configured MailerLite group.
Route::post('/newsletter', NewsletterController::class)->name('newsletter.subscribe');

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

// Public single-service pages, nested under their category. `scopeBindings` resolves
// the service within `category->services()` (404 if it belongs elsewhere). A service
// may override its default layout with a custom Mason page via `pageable`.
Route::get('/sluzby/{category:slug}/{service:slug}', [ServiceController::class, 'show'])
    ->scopeBindings()
    ->name('service.show');

// Public therapist profile pages, nested under the about page. Published profiles
// only; staff can preview drafts. Registered before the catch-all (which only
// matches a single path segment, so a two-segment /o-nas/{slug} never collides).
Route::get('/o-nas/{therapist:slug}', [TherapistController::class, 'show'])
    ->name('therapist.show');

// Public reservation wizard. One unified component; deep-link via query params
// (?sluzba= service slug, ?terapeut= therapist id, ?kategorie= category slug) to start
// with a service or therapist prefilled. State is query-string bound throughout.
Route::get('/rezervace', fn () => view('reservation.index'))->name('reservation.wizard');

// Public review form, reached only via the magic-link token sent in a review
// request e-mail. Two path segments, so it never collides with the /{slug} catch-all.
Route::get('/recenze/{token}', fn (string $token) => view('reviews.form', ['token' => $token]))
    ->name('reviews.form');

// Public login (web guard). Also the wizard's login fallback / forgotten-password entry.
Route::get('/prihlaseni', fn () => view('auth.login'))->name('public.login');

// Instagram OAuth handshake for the admin "Instagram účty" resource. The controller
// guards against guests (admin logs in via the Filament panel, which has no plain
// `login` route). Registered before the catch-all and excluded from it below.
Route::get('/instagram/authorize/{connection}', [InstagramOAuthController::class, 'redirect'])
    ->name('instagram.oauth.redirect');
Route::get('/instagram/callback', [InstagramOAuthController::class, 'callback'])
    ->name('instagram.oauth.callback');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|klientska-zona|livewire|passkeys|storage|dev|up|rezervace|prihlaseni|instagram).*$')
    ->name('page.show');
