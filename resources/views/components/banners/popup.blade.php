@php
    $content = $banner->content ?? [];
    $url = \App\Support\LinkResolver::fromConfig($content, 'cta_');
    $image = \App\Support\Media::url($content['image'] ?? null, '800');
@endphp

<div data-banner="{{ $banner->id }}" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/60 p-4">
    <div class="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
        <button type="button" data-banner-dismiss class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/80 text-neutral-700 transition hover:bg-white" aria-label="Zavřít">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        @if($image)
            <img src="{{ $image }}" alt="" class="aspect-[16/9] w-full object-cover">
        @endif

        <div class="p-8 text-center">
            @if(! empty($content['badge_text']))
                <span class="mb-3 inline-block rounded-full bg-primary-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary-dark">{{ $content['badge_text'] }}</span>
            @endif
            <h3 class="font-heading text-2xl font-bold text-neutral-900">{{ $content['title'] ?? '' }}</h3>
            @if(! empty($content['description']))
                <p class="mt-3 leading-relaxed text-neutral-600">{{ $content['description'] }}</p>
            @endif
            @if($url && ! empty($content['cta_text']))
                <a href="{{ $url }}" class="mt-6 inline-flex rounded-full bg-primary px-7 py-3 font-semibold text-white transition hover:bg-primary-dark">{{ $content['cta_text'] }}</a>
            @endif
        </div>
    </div>
</div>
