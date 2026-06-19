@php
    $config ??= [];
    $image = \App\Support\Media::url($config['image'] ?? null, '800');
    $features = $config['features'] ?? [];
    $buttons = $config['buttons'] ?? [];
@endphp

<section class="bg-surface-alt">
    <div class="ff-container flex flex-col items-center gap-12 py-16 lg:min-h-[600px] lg:flex-row lg:justify-between lg:py-0">
        <div class="flex w-full max-w-[600px] flex-col gap-6">
            @if(! empty($config['eyebrow']))
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $config['eyebrow'] }}</p>
            @endif

            <h1 class="font-heading text-4xl font-bold leading-[1.15] text-neutral-900 lg:text-5xl">{{ $config['title'] ?? '' }}</h1>

            @if(! empty($features))
                @if(is_array($features))
                    <ul class="flex flex-col gap-2.5">
                        @foreach($features as $feature)
                            <li class="flex items-center gap-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                                <span class="text-base text-neutral-900">{{ is_array($feature) ? ($feature['text'] ?? '') : $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="hero-features text-base text-neutral-900">{!! $features !!}</div>
                @endif
            @endif

            @if($buttons)
                <div class="flex flex-wrap gap-3">
                    @foreach($buttons as $btn)
                        @include('bricks.partials.button', ['btn' => $btn])
                    @endforeach
                </div>
            @endif
        </div>

        <div class="aspect-[56/52] w-full max-w-[560px] shrink-0 overflow-hidden rounded-2xl bg-primary-light lg:h-[520px] lg:w-[560px]">
            @if($image)
                <img src="{{ $image }}" alt="{{ $config['title'] ?? '' }}" class="h-full w-full object-cover">
            @endif
        </div>
    </div>
</section>
