@php
    $config ??= [];
    $buttons = $config['buttons'] ?? [];
@endphp

<section class="py-16 lg:py-24">
    <div class="ff-container">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
            @if(! empty($config['icon']))
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
                    {!! \App\Support\Icon::render($config['icon'], 'h-7 w-7') !!}
                </div>
            @endif

            <h2 class="font-heading text-2xl font-bold text-neutral-900 lg:text-3xl">{!! \App\Support\RichText::inline($config['title'] ?? '') !!}</h2>

            @if(! empty($config['body']))
                <p class="leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($config['body']) !!}</p>
            @endif

            @if(! empty($config['note']))
                <p class="font-semibold text-neutral-900">{!! \App\Support\RichText::inline($config['note']) !!}</p>
            @endif

            @if($buttons)
                <div class="mt-2 flex flex-wrap justify-center gap-3">
                    @foreach($buttons as $btn)
                        @include('bricks.partials.button', ['btn' => $btn])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
