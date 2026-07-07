@php($config ??= [])

<section class="bg-surface-alt">
    <div class="ff-container flex flex-col items-center gap-3 py-14 text-center lg:py-20">
        @if(! empty($config['eyebrow']))
            <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $config['eyebrow'] }}</p>
        @endif

        @if(! empty($config['title']))
            <h1 class="font-heading text-3xl font-bold text-neutral-900 lg:text-5xl">{!! \App\Support\RichText::inline($config['title']) !!}</h1>
        @endif

        @if(! empty($config['subtitle']))
            <p class="max-w-2xl leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($config['subtitle']) !!}</p>
        @endif
    </div>
</section>
