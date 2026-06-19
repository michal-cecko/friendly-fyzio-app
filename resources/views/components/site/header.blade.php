@php($items = $headerNav?->items ?? collect())

<header data-site-header class="sticky top-0 z-40 border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">
        <a href="{{ url('/') }}" class="flex items-center">
            <img src="{{ asset('logo/ff-logo-bright.svg') }}" alt="Friendly Fyzio" class="h-9 w-auto">
        </a>

        <nav class="hidden items-center gap-1 lg:flex">
            @foreach($items as $item)
                @if($item->children->isNotEmpty())
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-toggle class="inline-flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-surface-alt hover:text-primary">
                            {{ $item->label }}
                            <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div data-dropdown-menu class="absolute left-0 top-full mt-2 hidden min-w-56 rounded-2xl border border-line bg-white p-2 shadow-xl shadow-neutral-900/5">
                            @foreach($item->children as $child)
                                <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="block rounded-xl px-4 py-2.5 text-sm text-neutral-700 transition hover:bg-surface-alt hover:text-primary">{{ $child->label }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $item->resolvedUrl() ?? '#' }}" target="{{ $item->target }}" class="rounded-full px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-surface-alt hover:text-primary">{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <a href="{{ url('/klientska-zona') }}" class="hidden rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark sm:inline-flex">
                Klientská zóna
            </a>
            <button type="button" data-mobile-toggle class="inline-flex h-10 w-10 items-center justify-center rounded-full text-neutral-700 transition hover:bg-surface-alt lg:hidden" aria-label="Otevřít menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    <div data-mobile-menu class="hidden border-t border-line bg-white lg:hidden">
        <nav class="space-y-1 px-6 py-4">
            @foreach($items as $item)
                @if($item->children->isNotEmpty())
                    <p class="px-2 pt-3 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $item->label }}</p>
                    @foreach($item->children as $child)
                        <a href="{{ $child->resolvedUrl() ?? '#' }}" target="{{ $child->target }}" class="block rounded-lg px-2 py-2 text-neutral-700 transition hover:text-primary">{{ $child->label }}</a>
                    @endforeach
                @else
                    <a href="{{ $item->resolvedUrl() ?? '#' }}" target="{{ $item->target }}" class="block rounded-lg px-2 py-2 font-medium text-neutral-700 transition hover:text-primary">{{ $item->label }}</a>
                @endif
            @endforeach
            <a href="{{ url('/klientska-zona') }}" class="mt-3 block rounded-full bg-primary px-5 py-3 text-center font-semibold text-white">Klientská zóna</a>
        </nav>
    </div>
</header>
