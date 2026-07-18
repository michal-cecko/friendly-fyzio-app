@props(['adminEditUrl' => null])

@php
    $items = $headerNav?->items ?? collect();
    $user = auth()->user();
    $loginUrl = route('public.login');
    $registerUrl = route('public.register');
    $logoutUrl = route('logout');
    $bookingUrl = route('reservation.wizard');

    // Staff land in the admin panel; customers in the public client zone. The
    // account link reflects that (label + destination), driven by the account type.
    $isStaff = $user?->isStaff() ?? false;
    $accountUrl = $isStaff ? url('/admin') : url('/muj-ucet');
    $accountLabel = $isStaff ? 'Administrace' : 'Můj účet';

    // The client-zone sections mirrored into the account dropdown.
    $zoneLinks = $isStaff ? [] : [
        ['url' => url('/muj-ucet/rezervace'), 'label' => 'Moje rezervace'],
        ['url' => url('/muj-ucet/kurzy'), 'label' => 'Moje kurzy'],
        ['url' => url('/muj-ucet/nahrady'), 'label' => 'Náhradní vstupy'],
        ['url' => url('/muj-ucet/kredity'), 'label' => 'Kredity'],
        ['url' => url('/muj-ucet/platby'), 'label' => 'Platby'],
        ['url' => url('/muj-ucet/profil'), 'label' => 'Můj profil'],
    ];

    // Icon snippets (lucide, 24x24). Stroke inherits currentColor so size/color
    // is controlled by the wrapping element's font-size and text color utilities.
    $icon = [
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'log-in' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>',
        'menu' => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
    ];
@endphp

<header data-site-header class="sticky top-0 z-40 border-b border-line bg-white">
    {{-- ============================ DESKTOP (lg+) ============================ --}}
    <div class="hidden lg:block">
        {{-- Row 1 — logo + auth / booking CTA --}}
        <div class="ff-container flex items-center justify-between py-4">
            <a href="{{ url('/') }}" class="flex items-center font-heading text-[22px] leading-none">
                <span class="font-medium text-neutral-900">Friendly</span>
                <span class="font-semibold italic text-primary">Fyzio</span>
            </a>

            <div class="flex items-center gap-3">
                @if($user)
                    <a href="{{ $bookingUrl }}" class="inline-flex items-center gap-2 rounded-full bg-primary px-[18px] py-2 text-[13px] font-semibold text-white transition hover:bg-primary-hover">
                        Chci se objednat
                    </a>
                    <span class="h-6 w-px bg-line"></span>
                    <div class="group relative">
                        <button type="button" class="flex items-center gap-3 text-sm font-medium text-neutral-900">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-light text-primary">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['user'] !!}</svg>
                            </span>
                            <span>{{ $user->name }}</span>
                            <svg class="h-4 w-4 text-neutral-500 transition group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-down'] !!}</svg>
                        </button>
                        <div class="absolute right-0 top-full hidden pt-2 group-hover:block group-focus-within:block">
                            <div class="min-w-52 rounded-xl border border-line bg-white p-2 shadow-lg shadow-neutral-900/10">
                                <a href="{{ $accountUrl }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['layout-dashboard'] !!}</svg>
                                    {{ $accountLabel }}
                                </a>
                                @foreach($zoneLinks as $zoneLink)
                                    <a href="{{ $zoneLink['url'] }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                        <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-right'] !!}</svg>
                                        {{ $zoneLink['label'] }}
                                    </a>
                                @endforeach
                                @if($adminEditUrl)
                                    <a href="{{ $adminEditUrl }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['pencil'] !!}</svg>
                                        Upravit tuto stránku
                                    </a>
                                @endif
                                <div class="my-1 h-px bg-line"></div>
                                <form method="POST" action="{{ $logoutUrl }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['log-out'] !!}</svg>
                                        Odhlásit se
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $loginUrl }}" class="inline-flex items-center gap-2 rounded-full px-[18px] py-2 text-[13px] font-medium text-neutral-700 transition hover:bg-surface-alt hover:text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['log-in'] !!}</svg>
                        Přihlásit se
                    </a>
                    <span class="h-6 w-px bg-line"></span>
                    <a href="{{ $bookingUrl }}" class="inline-flex items-center gap-2 rounded-full bg-primary px-[18px] py-2 text-[13px] font-semibold text-white transition hover:bg-primary-hover">
                        Chci se objednat
                    </a>
                @endif
            </div>
        </div>

        {{-- Row 2 — centered primary navigation --}}
        <div class="border-t border-line">
            <nav class="ff-container flex items-center justify-center gap-8 py-3">
                @foreach($items as $item)
                    @if($item->children->isNotEmpty())
                        <div class="group relative">
                            <a href="{{ $item->resolvedUrl() ?? '#' }}" class="inline-flex items-center gap-1 text-sm font-medium text-neutral-900 transition hover:text-primary">
                                {{ $item->label }}
                                <svg class="h-3.5 w-3.5 text-neutral-500 transition group-hover:rotate-180 group-hover:text-primary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-down'] !!}</svg>
                            </a>
                            {{-- pt-2 bridges the gap so the dropdown stays open while moving the cursor into it --}}
                            <div class="absolute left-1/2 top-full hidden -translate-x-1/2 pt-3 group-hover:block group-focus-within:block">
                                <div class="w-60 rounded-xl border border-line bg-white p-2 shadow-lg shadow-neutral-900/10">
                                    @foreach($item->children as $child)
                                        <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-right'] !!}</svg>
                                            {{ $child->label }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->resolvedUrl() ?? '#' }}" target="{{ $item->target }}" class="text-sm font-medium text-neutral-900 transition hover:text-primary">{{ $item->label }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>

    {{-- ============================ MOBILE (< lg) ============================ --}}
    <div class="lg:hidden">
        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ url('/') }}" class="flex items-center font-heading text-lg leading-none">
                <span class="font-medium text-neutral-900">Friendly</span>
                <span class="font-semibold italic text-primary">Fyzio</span>
            </a>

            <div class="flex items-center gap-3">
                <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white">
                    Objednat
                </a>
                <button type="button" data-mobile-toggle class="inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-900" aria-label="Otevřít menu" aria-expanded="false">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['menu'] !!}</svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile overlay menu --}}
    <div data-mobile-menu class="fixed inset-x-0 bottom-0 top-16 z-50 hidden overflow-y-auto border-t border-line bg-white lg:hidden">
        <div class="flex flex-col gap-5 p-6">
            <div>
                @foreach($items as $item)
                    @if($item->children->isNotEmpty())
                        <div data-accordion>
                            <button type="button" data-accordion-trigger class="flex w-full items-center justify-between border-b border-line py-[18px] text-left">
                                <span data-accordion-label class="font-heading text-lg font-medium text-neutral-900">{{ $item->label }}</span>
                                <svg data-accordion-icon class="h-5 w-5 shrink-0 text-neutral-400 transition-transform" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-right'] !!}</svg>
                            </button>
                            <div data-accordion-panel class="hidden">
                                <div class="my-2 flex flex-col rounded-xl bg-surface-alt px-4 py-2">
                                    @foreach($item->children as $child)
                                        <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm text-neutral-900 transition hover:bg-primary-light hover:text-primary">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-right'] !!}</svg>
                                            {{ $child->label }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->resolvedUrl() ?? '#' }}" target="{{ $item->target }}" class="flex items-center justify-between border-b border-line py-[18px]">
                            <span class="font-heading text-lg font-medium text-neutral-900">{{ $item->label }}</span>
                            <svg class="h-5 w-5 shrink-0 text-neutral-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['chevron-right'] !!}</svg>
                        </a>
                    @endif
                @endforeach
            </div>

            @if($user)
                <form method="POST" action="{{ $logoutUrl }}" class="flex flex-col gap-3">
                    @csrf
                    <a href="{{ $accountUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-line py-3 text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['layout-dashboard'] !!}</svg>
                        {{ $accountLabel }}
                    </a>
                    @if($adminEditUrl)
                        <a href="{{ $adminEditUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-line py-3 text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['pencil'] !!}</svg>
                            Upravit tuto stránku
                        </a>
                    @endif
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-surface-alt py-3 text-sm font-semibold text-neutral-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['log-out'] !!}</svg>
                        Odhlásit se
                    </button>
                </form>
            @else
                <div class="flex gap-3">
                    <a href="{{ $loginUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-line py-3 text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['log-in'] !!}</svg>
                        Přihlásit se
                    </a>
                    <a href="{{ $registerUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-line py-3 text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['user-plus'] !!}</svg>
                        Registrace
                    </a>
                </div>
            @endif

            <a href="{{ $bookingUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary py-4 text-base font-semibold text-white transition hover:bg-primary-hover">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon['calendar'] !!}</svg>
                Chci se objednat
            </a>

            <div class="flex flex-col gap-2">
                <a href="tel:+420604793255" class="text-[15px] font-semibold text-primary">+420 604 793 255</a>
                <a href="mailto:info@friendlyfyzio.cz" class="text-sm text-neutral-500">info@friendlyfyzio.cz</a>
            </div>
        </div>
    </div>
</header>
