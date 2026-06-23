<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Http\Middleware\RedirectStaffToAdmin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcelWeidum\Passkeys\PasskeysPlugin;

/**
 * Customer-facing "klientská zóna". Hosts the single shared login for the whole
 * app (staff are redirected to the admin panel after authenticating — see
 * App\Http\Responses\LoginResponse) plus open registration and email verification.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('klientska-zona')
            ->viteTheme('resources/css/filament/client/theme.css')
            ->brandName('FriendlyFyzio')
            ->brandLogo(asset('logo/ff-logo-bright.svg'))
            ->darkModeBrandLogo(asset('logo/ff-logo-dark.svg'))
            ->brandLogoHeight('1.6rem')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->profile()
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->colors([
                'primary' => Color::hex('#d4678a'),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                PasskeysPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Staff belong in the admin panel — bounce them out of the client zone.
                RedirectStaffToAdmin::class,
            ]);
    }
}
