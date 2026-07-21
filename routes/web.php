<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\InstagramOAuthController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OneOffEventController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Pdf\CustomerInvoiceDownloadController;
use App\Http\Controllers\Pdf\InvoiceExportDownloadController;
use App\Http\Controllers\Pdf\InvoicePreviewController;
use App\Http\Controllers\Pdf\ReceiptPreviewController;
use App\Http\Controllers\ReservationManageController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TherapistController;
use App\Http\Middleware\EnsureZoneCustomer;
use App\Models\Reservation;
use App\Support\Seo\LegacyRedirects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Passkey (WebAuthn) authentication endpoints used by the Filament login page.
Route::passkeys();

// Newsletter signup — subscribes the email to the configured MailerLite group.
Route::post('/newsletter', NewsletterController::class)->name('newsletter.subscribe');

// Public CMS pages. The homepage is the system page keyed "home"; every other
// published page resolves by slug. Reserved prefixes (Filament panels, Livewire,
// passkeys, storage, health) are excluded from the catch-all.
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

// Public course detail pages. The /kurzy archive is a CMS page (served by the
// catch-all) and one-off event categories (/workshopy, /jednorazove-lekce, …)
// resolve inside PageController; event details are the two-segment catch-all
// near the bottom. A course's hidden pre-sale link (?predprodej=token) unlocks
// an otherwise closed course page.
Route::get('/kurzy/{course:slug}', [CourseController::class, 'show'])
    ->name('course.show');

// Public reservation wizard. One unified component; deep-link via query params
// (?sluzba= service slug, ?terapeut= therapist slug, ?kategorie= category slug) to start
// with a service or therapist prefilled. State is query-string bound throughout.
Route::get('/rezervace', fn () => view('reservation.index'))->name('reservation.wizard');

// Customer self-service for a reservation via one signed magic link: the passwordless
// "manage" page hosting confirm, free cancel, and the late-cancel storno decision. GET
// only shows the page; POST performs the chosen action. Both share one URI so a single
// signature validates them.
Route::get('/rezervace/spravovat/{reservation}', [ReservationManageController::class, 'show'])
    ->middleware('signed')
    ->name('reservation.manage');
Route::post('/rezervace/spravovat/{reservation}', [ReservationManageController::class, 'submit'])
    ->middleware('signed')
    ->name('reservation.manage.submit');

// Public review form, reached only via the magic-link token sent in a review
// request e-mail. Two path segments, so it never collides with the /{slug} catch-all.
Route::get('/recenze/{token}', fn (string $token) => view('reviews.form', ['token' => $token]))
    ->name('reviews.form');

// Public auth (web guard): login, self-service registration, password reset and
// e-mail verification — the whole customer auth surface (staff use /admin/login).
// Route names matter: `verified` middleware and the reset-link notification build
// URLs from verification.notice / verification.verify / password.reset.
Route::get('/prihlaseni', fn () => view('auth.login'))->name('public.login');
Route::get('/registrace', fn () => view('auth.register'))->name('public.register');
Route::get('/prihlaseni/zapomenute-heslo', fn () => view('auth.forgot-password'))->name('password.request');
Route::get('/prihlaseni/obnova-hesla/{token}', fn (string $token) => view('auth.reset-password', ['token' => $token]))->name('password.reset');
Route::get('/overeni-emailu', fn () => view('auth.verify-email'))
    ->middleware('auth')
    ->name('verification.notice');
Route::get('/overeni-emailu/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/odhlaseni', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to('/');
})->name('logout');

// The client zone used to live in a Filament panel at /klientska-zona; keep old
// links (delivered e-mails, bookmarks) working.
Route::redirect('/klientska-zona', '/muj-ucet', 301);
Route::get('/klientska-zona/{any}', fn () => redirect('/muj-ucet', 301))->where('any', '.*');

// Klientská zóna — the authenticated customer area ("Můj účet"). Verified,
// non-deactivated customers only; staff are bounced to /admin. Every page is a
// Livewire component inside the shared zone layout; ownership checks live in
// the components (foreign records 404).
Route::middleware(['auth', 'verified', EnsureZoneCustomer::class])
    ->prefix('muj-ucet')
    ->name('zone.')
    ->group(function (): void {
        Route::get('/', fn () => view('zone.dashboard', ['seo' => ['title' => 'Můj účet'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => null]]]))->name('dashboard');
        Route::get('/rezervace', fn () => view('zone.reservations', ['seo' => ['title' => 'Moje rezervace'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Moje rezervace', 'url' => null]]]))->name('reservations');
        Route::get('/rezervace/{reservation}', fn (Reservation $reservation) => view('zone.reservation-detail', [
            'seo' => ['title' => 'Detail rezervace'],
            'reservation' => $reservation,
            'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Moje rezervace', 'url' => url('/muj-ucet/rezervace')], ['label' => 'Detail rezervace', 'url' => null]],
        ]))->name('reservations.show');
        Route::get('/rezervace/{reservation}/presunout', fn (Reservation $reservation) => view('zone.reservation-reschedule', [
            'seo' => ['title' => 'Přesunout termín'],
            'reservation' => $reservation,
            'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Moje rezervace', 'url' => url('/muj-ucet/rezervace')], ['label' => 'Přesunout termín', 'url' => null]],
        ]))->name('reservations.reschedule');
        Route::get('/kurzy', fn () => view('zone.courses', ['seo' => ['title' => 'Moje kurzy'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Moje kurzy', 'url' => null]]]))->name('courses');
        Route::get('/nahrady', fn () => view('zone.tokens', ['seo' => ['title' => 'Náhradní vstupy'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Náhradní vstupy', 'url' => null]]]))->name('tokens');
        Route::get('/kredity', fn () => view('zone.credits', ['seo' => ['title' => 'Kredity'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Kredity', 'url' => null]]]))->name('credits');
        Route::get('/platby', fn () => view('zone.payments', ['seo' => ['title' => 'Platby'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Platby', 'url' => null]]]))->name('payments');
        Route::get('/faktury', fn () => view('zone.invoices', ['seo' => ['title' => 'Faktury'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Faktury', 'url' => null]]]))->name('invoices');
        Route::get('/faktury/{invoice}/stahnout', CustomerInvoiceDownloadController::class)->name('invoices.download');
        Route::get('/profil', fn () => view('zone.profile', ['seo' => ['title' => 'Můj profil'], 'breadcrumbs' => [['label' => 'Můj účet', 'url' => url('/muj-ucet')], ['label' => 'Můj profil', 'url' => null]]]))->name('profile');
    });

// Instagram OAuth handshake for the admin "Instagram účty" resource. The controller
// guards against guests (admin logs in via the Filament panel, which has no plain
// `login` route). Registered before the catch-all and excluded from it below.
Route::get('/instagram/authorize/{connection}', [InstagramOAuthController::class, 'redirect'])
    ->name('instagram.oauth.redirect');
Route::get('/instagram/callback', [InstagramOAuthController::class, 'callback'])
    ->name('instagram.oauth.callback');

// Staff-only HTML previews of the PDF documents (the exact markup Gotenberg
// renders). Authorization happens in the controllers (guests get 403).
Route::get('/nahledy/faktura/{invoice}', InvoicePreviewController::class)
    ->name('invoices.preview');
Route::get('/nahledy/pokladni-doklad/{cashReceipt}', ReceiptPreviewController::class)
    ->name('cash-receipts.preview');

// Download of a background-built invoice ZIP (path is a base64 pointer into the
// private disk's invoice-exports/ folder; pruned after 24 h).
Route::get('/nahledy/export-faktur', InvoiceExportDownloadController::class)
    ->name('invoices.export-download');

// Public XML sitemap — every canonical public URL, built from model permalinks.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// One-off event detail pages nest under their category slug (/workshopy/{slug},
// /jednorazove-lekce/{slug}, …). Categories are data, so this is a two-segment
// catch-all; it must precede page.show, whose `.*` pattern also swallows
// multi-segment paths. The controller resolves the pair manually and falls
// through to LegacyRedirects for unknown two-segment paths (which previously
// reached PageController — e.g. /relaxace-ritualy/masaze).
Route::get('/{category}/{event}', [OneOffEventController::class, 'show'])
    ->where([
        'category' => '(?!(?:admin|kurzy|sluzby|o-nas|rezervace|recenze|nahledy|muj-ucet|klientska-zona|prihlaseni|registrace|overeni-emailu|odhlaseni|instagram|livewire|passkeys|storage|up)/)[^/]+',
        'event' => '[^/]+',
    ])
    ->name('one-off-event.show');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|klientska-zona|muj-ucet|livewire|passkeys|storage|up|rezervace|prihlaseni|registrace|overeni-emailu|odhlaseni|instagram|nahledy).*$')
    ->name('page.show');

// Old live-site URLs with 3+ segments (e.g. /kurzy/joga/lekce/{id}) end up in
// page.show's catch-all and resolve through LegacyRedirects there; this
// fallback only catches paths excluded from every pattern above.
Route::fallback(function (Request $request) {
    $target = LegacyRedirects::resolve($request->path());

    abort_if($target === null, 404);

    return redirect()->to($target, 301);
});
