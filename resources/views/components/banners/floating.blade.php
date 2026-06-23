@php
    $content = $banner->content ?? [];
    $url = \App\Support\LinkResolver::fromConfig($content, 'cta_');
@endphp

<div data-banner="{{ $banner->id }}" data-banner-delay="2000" class="pointer-events-none invisible fixed bottom-5 right-5 z-50 w-80 max-w-[calc(100vw-2.5rem)] rounded-2xl border border-line bg-white p-5 opacity-0 shadow-2xl shadow-neutral-900/10 transition-opacity duration-500">
    <button type="button" data-banner-dismiss class="absolute right-3 top-3 text-neutral-400 transition hover:text-neutral-700" aria-label="Zavřít">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div class="flex items-start gap-3 pr-4">
        @if(! empty($content['icon']))
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-light text-primary-dark">
                {!! \App\Support\Icon::render($content['icon'], 'h-5 w-5') !!}
            </span>
        @endif
        <div>
            <h3 class="font-heading font-bold text-neutral-900">{{ $content['title'] ?? '' }}</h3>
            @if(! empty($content['description']))
                <p class="mt-1 text-sm leading-relaxed text-neutral-600">{{ $content['description'] }}</p>
            @endif
        </div>
    </div>

    @if($url && ! empty($content['cta_text']))
        <a href="{{ $url }}" class="mt-4 block rounded-full bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-primary-dark">
            {{ $content['cta_text'] }}
        </a>
    @endif
</div>
