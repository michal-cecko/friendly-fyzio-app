<?php

namespace App\Providers\Filament;

use App\Filament\Livewire\DatabaseNotifications;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\Search\PanelGlobalSearchProvider;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use DiscoveryDesign\FilamentGaze\FilamentGazePlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use MarcelWeidum\ExpirationNoticePlugin\ExpirationNoticePlugin;
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
            // Staff sign in here (customers live on the public /prihlaseni page
            // and are rejected by User::canAccessPanel()).
            ->login(Login::class)
            ->passwordReset()
            ->emailChangeVerification()
            ->profile()
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            // Receipts for queued sends (e.g. writing to a whole série) are filed
            // by a worker, with no session to toast into — they only appear when
            // the bell polls. Filament's 30s default leaves the admin watching
            // an empty bell for too long after they pressed send.
            ->databaseNotificationsPolling('10s')
            // Our subclass reuses that poll to announce new notifications as
            // toasts, and stops a dismissed toast from deleting its row.
            ->databaseNotificationsLivewireComponent(DatabaseNotifications::class)
            ->sidebarCollapsibleOnDesktop()
            // Narrower than Filament's 20rem: no label here comes close to filling
            // that, and inside a cluster the page carries a second (sub-navigation)
            // sidebar too — together they were eating nearly half the window and
            // squeezing the tables and stat cards next to them. The sub-navigation
            // is matched to this width in the admin theme (it has no PHP setting).
            ->sidebarWidth('12rem')
            ->collapsibleNavigationGroups()
            ->maxContentWidth(Width::Full)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // The topbar search searches settings and the manual too, matching what
            // the standalone search page returns.
            ->globalSearch(PanelGlobalSearchProvider::class)
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.global-search-page-link')->render(),
            )
            // Problémy and Návrhy live here rather than in the sidebar — what is
            // wrong and what is undone, glanced at from anywhere in the panel.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.problems-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.suggestions-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.website-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.topbar.contact-inquiries-link')->render(),
            )
            // The in-app manual is pinned below the navigation rather than placed in
            // it: SIDEBAR_FOOTER renders outside the scrolling nav, so Nápověda stays
            // reachable however long the sidebar grows and whatever it gets sorted by.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.sidebar.help-link')->render(),
            )
            // Records the window width so the server can render viewport-dependent
            // layout on the first paint ({@see App\Filament\Support\Viewport}).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.viewport-hint')->render(),
            )
            ->colors([
                'primary' => Color::hex('#d4678a'),
            ])
            // discoverClusters() already discovers the pages and resources inside the
            // clusters directory — re-discovering it would double every cluster's
            // sub-navigation items.
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
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
                FilamentShieldPlugin::make(),
                FilamentMediaLibrary::make()
                    ->registerNavigation(false),
                FilamentFullCalendarPlugin::make(),
                FilamentApexChartsPlugin::make(),
                PasskeysPlugin::make(),
                ExpirationNoticePlugin::make(),
                FilamentGazePlugin::make(),
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
            ]);
    }
}
