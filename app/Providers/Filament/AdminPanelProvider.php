<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RedirectToClientLogin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcelWeidum\Passkeys\PasskeysPlugin;
use RalphJSmit\Filament\MediaLibrary\FilamentMediaLibrary;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('FriendlyFyzio')
            ->brandLogo(asset('logo/ff-logo-bright.svg'))
            ->darkModeBrandLogo(asset('logo/ff-logo-dark.svg'))
            ->brandLogoHeight('1.6rem')
            ->emailChangeVerification()
            ->profile()
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.website-link')->render(),
            )
            ->colors([
                'primary' => Color::hex('#d4678a'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Správa přístupů'),
                FilamentMediaLibrary::make()
                    ->navigationGroup('Média')
                    ->navigationLabel('Knihovna médií'),
                FilamentFullCalendarPlugin::make(),
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
                // No login page of its own — bounce unauthenticated staff to the
                // single shared login on the client panel.
                RedirectToClientLogin::class,
            ]);
    }
}
