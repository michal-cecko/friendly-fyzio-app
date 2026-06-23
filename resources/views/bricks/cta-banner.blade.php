@php
    $config ??= [];
    $buttons = $config['buttons'] ?? [];
@endphp

<section class="bg-gradient-to-b from-primary to-primary-dark py-16 text-center">
    <div class="ff-container">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6">
            @if(! empty($config['eyebrow']))
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-white/60">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="font-heading text-3xl font-bold text-white">{!! \App\Support\RichText::inline($config['title'] ?? '') !!}</h2>
            @if(! empty($config['subtitle']))
                <p class="leading-relaxed text-white/90">{!! \App\Support\RichText::inline($config['subtitle']) !!}</p>
            @endif

            @if($buttons)
                <div class="mt-2 flex flex-wrap justify-center gap-4">
                    @foreach($buttons as $btn)
                        @include('bricks.partials.button', ['btn' => $btn])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
