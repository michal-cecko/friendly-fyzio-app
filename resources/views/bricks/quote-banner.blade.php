@php($config ??= [])

<section class="bg-primary py-16 text-center">
    <div class="ff-container flex flex-col items-center gap-4">
        <p class="mx-auto max-w-3xl font-heading text-2xl font-bold leading-snug tracking-wide text-white">{!! \App\Support\RichText::inline($config['text'] ?? '') !!}</p>

        @if(! empty($config['icon']))
            <div class="text-white/60">
                {!! \App\Support\Icon::render($config['icon'], 'h-8 w-8') !!}
            </div>
        @endif
    </div>
</section>
