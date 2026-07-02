@props([
    'items' => null,
    'page' => null,
])

@php
    // Explicit trail wins; otherwise fall back to a minimal "Domů > current page"
    // trail derived from the current page's title. Items must NOT include the
    // leading "Domů" crumb — it is always rendered first below.
    $trail = $items ?: array_values(array_filter([
        $page && ($page->title ?? null) ? ['label' => $page->title, 'url' => null] : null,
    ]));
@endphp

@if(! empty($trail))
    <nav aria-label="Drobečková navigace" class="border-b border-line bg-white">
        <div class="ff-container flex flex-wrap items-center gap-2 py-4 text-sm text-neutral-500">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Domů</a>
            @foreach($trail as $crumb)
                <x-lucide name="chevron-right" class="h-4 w-4 text-neutral-300" />
                @if(! empty($crumb['url']) && ! $loop->last)
                    <a href="{{ $crumb['url'] }}" class="transition hover:text-primary">{{ $crumb['label'] }}</a>
                @else
                    <span class="font-medium text-neutral-900">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </div>
    </nav>
@endif
