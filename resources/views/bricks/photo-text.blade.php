@php
    $config ??= [];
    $image = \App\Support\Media::url($config['image'] ?? null, '800');
    $buttons = $config['buttons'] ?? [];
    $imageRight = ($config['image_position'] ?? 'left') === 'right';
@endphp

<section class="py-16 lg:py-24">
    <div class="ff-container">
        <div class="grid overflow-hidden rounded-2xl lg:grid-cols-2">
            <div class="min-h-[280px] bg-primary-light {{ $imageRight ? 'lg:order-2' : '' }}">
                @if($image)
                    <img src="{{ $image }}" alt="{{ strip_tags($config['title'] ?? '') }}" class="h-full min-h-[280px] w-full object-cover">
                @endif
            </div>

            <div class="flex flex-col justify-center gap-4 bg-surface-alt p-8 lg:p-12 {{ $imageRight ? 'lg:order-1' : '' }}">
                @if(! empty($config['eyebrow']))
                    <p class="font-heading text-sm font-semibold uppercase tracking-[0.12em] text-primary">{{ $config['eyebrow'] }}</p>
                @endif

                <h2 class="font-heading text-2xl font-bold text-neutral-900 lg:text-3xl">{!! \App\Support\RichText::inline($config['title'] ?? '') !!}</h2>

                @if(! empty($config['body']))
                    <div class="ff-prose text-neutral-600">{!! $config['body'] !!}</div>
                @endif

                @if($buttons)
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach($buttons as $btn)
                            @include('bricks.partials.button', ['btn' => $btn])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
