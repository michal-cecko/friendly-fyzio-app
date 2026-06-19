@php
    $config ??= [];
    $image = \App\Support\Media::url($config['image'] ?? null, '800');
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
    $cta2Url = \App\Support\LinkResolver::fromConfig($config, 'secondary_cta_');
@endphp

<section class="bg-surface-alt">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:px-8 lg:py-24">
        <div>
            @if(! empty($config['badge']))
                <span class="mb-6 inline-block rounded-full bg-primary-light px-4 py-1.5 text-sm font-semibold text-primary-dark">
                    {{ $config['badge'] }}
                </span>
            @endif

            <h1 class="font-heading text-4xl font-extrabold leading-tight text-neutral-900 lg:text-5xl">
                {{ $config['title'] ?? '' }}
                @if(! empty($config['title_accent']))
                    <span class="text-primary">{{ $config['title_accent'] }}</span>
                @endif
            </h1>

            @if(! empty($config['subtitle']))
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-600">{{ $config['subtitle'] }}</p>
            @endif

            @if(($ctaUrl && ! empty($config['cta_text'])) || ($cta2Url && ! empty($config['secondary_cta_text'])))
                <div class="mt-8 flex flex-wrap gap-4">
                    @if($ctaUrl && ! empty($config['cta_text']))
                        <a href="{{ $ctaUrl }}" class="inline-flex items-center rounded-full bg-primary px-7 py-3.5 font-semibold text-white transition hover:bg-primary-dark">
                            {{ $config['cta_text'] }}
                        </a>
                    @endif
                    @if($cta2Url && ! empty($config['secondary_cta_text']))
                        <a href="{{ $cta2Url }}" class="inline-flex items-center rounded-full border border-line px-7 py-3.5 font-semibold text-neutral-900 transition hover:border-primary hover:text-primary">
                            {{ $config['secondary_cta_text'] }}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="relative">
            @if($image)
                <img src="{{ $image }}" alt="{{ $config['title'] ?? '' }}" class="aspect-[4/5] w-full rounded-3xl object-cover lg:aspect-[5/6]">
            @else
                <div class="aspect-[4/5] w-full rounded-3xl bg-primary-light lg:aspect-[5/6]"></div>
            @endif
        </div>
    </div>
</section>
