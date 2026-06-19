@php
    $config ??= [];
    $ctaUrl = \App\Support\LinkResolver::fromConfig($config, 'cta_');
    $cta2Url = \App\Support\LinkResolver::fromConfig($config, 'secondary_cta_');
@endphp

<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-br from-primary to-primary-dark px-8 py-14 text-center lg:px-16">
            @if(! empty($config['eyebrow']))
                <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-white/80">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="mx-auto max-w-3xl font-heading text-3xl font-bold text-white lg:text-4xl">{{ $config['title'] ?? '' }}</h2>
            @if(! empty($config['subtitle']))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-white/90">{{ $config['subtitle'] }}</p>
            @endif

            @if(($ctaUrl && ! empty($config['cta_text'])) || ($cta2Url && ! empty($config['secondary_cta_text'])))
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    @if($ctaUrl && ! empty($config['cta_text']))
                        <a href="{{ $ctaUrl }}" class="inline-flex items-center rounded-full bg-white px-7 py-3.5 font-semibold text-primary-dark transition hover:bg-white/90">{{ $config['cta_text'] }}</a>
                    @endif
                    @if($cta2Url && ! empty($config['secondary_cta_text']))
                        <a href="{{ $cta2Url }}" class="inline-flex items-center rounded-full border border-white/60 px-7 py-3.5 font-semibold text-white transition hover:bg-white/10">{{ $config['secondary_cta_text'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
