@php
    $content = $banner->content ?? [];
    $bg = $content['bg_color'] ?? null;
    $url = \App\Support\LinkResolver::fromConfig($content, 'cta_');
@endphp

<div data-banner="{{ $banner->id }}" class="relative text-white" style="background: {{ $bg ?: 'var(--color-primary)' }}">
    <div class="ff-container flex items-center justify-center gap-3 py-2.5 text-center text-sm">
        @if(! empty($content['icon']))
            {!! \App\Support\Icon::render($content['icon'], 'h-4 w-4 shrink-0') !!}
        @endif
        <span class="font-medium">{{ $content['text'] ?? '' }}</span>
        @if($url && ! empty($content['cta_text']))
            <a href="{{ $url }}" class="inline-flex shrink-0 items-center gap-1 font-semibold underline underline-offset-2 transition hover:opacity-90">
                @if(! empty($content['cta_icon']))
                    {!! \App\Support\Icon::render($content['cta_icon'], 'h-4 w-4 shrink-0') !!}
                @endif
                {{ $content['cta_text'] }} <span aria-hidden="true">&rarr;</span>
            </a>
        @endif
    </div>
    <button type="button" data-banner-dismiss class="absolute right-4 top-1/2 -translate-y-1/2 opacity-80 transition hover:opacity-100" aria-label="Zavřít">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
