@props([
    'after' => null,
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $renderHookScopes = $livewire?->getRenderHookScopes();
    $maxContentWidth ??= (filament()->getSimplePageMaxContentWidth() ?? Width::Large);

    if (is_string($maxContentWidth)) {
        $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
    }

    $brandName = filament()->getBrandName();

    // Staff auth pages (login, password reset) share the welcome image; customer
    // registration lives on the public site now.
    $authPhoto = asset('images/auth/auth-login.jpg');
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <style>
        .ff-auth {
            display: flex;
            min-height: 100dvh;
            width: 100%;
        }

        .ff-auth-main {
            flex: 1 1 50%;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: #ffffff;
        }

        .ff-auth-main-inner {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        .ff-auth-form {
            width: 100%;
            max-width: 25rem;
        }

        .ff-auth-logo {
            display: block;
            height: 2rem;
            width: auto;
            margin: 0 auto 2.25rem;
        }

        .ff-auth-main .fi-simple-main {
            box-shadow: none;
            background-color: transparent;
            width: 100%;
            padding: 0;
            --tw-ring-shadow: 0 0 #0000;
        }

        .ff-auth-main .fi-simple-main-ctn {
            padding: 0;
        }

        .ff-auth-photo {
            flex: 1 1 50%;
            background-image: var(--ff-auth-photo);
            background-size: cover;
            background-position: center;
            background-color: #d4678a;
        }

        @media (max-width: 1023px) {
            .ff-auth-photo {
                display: none;
            }
        }
    </style>

    <div class="ff-auth">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <div class="ff-auth-main">
            @if (($hasTopbar ?? true) && filament()->auth()->check())
                <div class="fi-simple-layout-header">
                    @if (filament()->hasDatabaseNotifications())
                        @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                            'position' => \Filament\Enums\DatabaseNotificationsPosition::Topbar,
                        ])
                    @endif

                    @if (filament()->hasUserMenu())
                        @livewire(Filament\Livewire\SimpleUserMenu::class)
                    @endif
                </div>
            @endif

            <div class="ff-auth-main-inner">
                <div class="ff-auth-form">
                    <img
                        class="ff-auth-logo"
                        src="{{ asset('logo/ff-logo-bright.svg') }}"
                        alt="{{ $brandName }}"
                    />

                    <div class="fi-simple-main-ctn">
                        <main
                            @class([
                                'fi-simple-main',
                                ($maxContentWidth instanceof Width) ? "fi-width-{$maxContentWidth->value}" : $maxContentWidth,
                            ])
                        >
                            {{ $slot }}
                        </main>
                    </div>
                </div>
            </div>
        </div>

        <aside class="ff-auth-photo" style="--ff-auth-photo: url('{{ $authPhoto }}')" aria-hidden="true"></aside>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
