@php
    $config ??= [];
    $images = $config['images'] ?? [];
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
@endphp

<section class="py-16 lg:py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        @include('bricks.partials.heading', ['config' => $config])

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @forelse($images as $imageId)
                @php($src = \App\Support\Media::url($imageId, '400'))
                <div class="aspect-square overflow-hidden rounded-xl bg-primary-light">
                    @if($src)
                        <img src="{{ $src }}" alt="" class="h-full w-full object-cover transition hover:opacity-90">
                    @endif
                </div>
            @empty
                @for($i = 0; $i < 6; $i++)
                    <div class="aspect-square rounded-xl bg-primary-light"></div>
                @endfor
            @endforelse
        </div>

        @if($ctaUrl && ! empty($config['cta_text']))
            <div class="mt-10 text-center">
                <a href="{{ $ctaUrl }}" class="inline-flex items-center gap-2 rounded-full border border-line px-7 py-3.5 font-semibold text-neutral-900 transition hover:border-primary hover:text-primary">{{ $config['cta_text'] }}</a>
            </div>
        @endif
    </div>
</section>
