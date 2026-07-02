@php
    $config ??= [];
    $steps = $config['steps'] ?? [];
    $count = count($steps);
    $gridClass = $count >= 4 ? 'lg:grid-cols-4' : ($count === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2');
@endphp

<section class="bg-surface-alt py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($steps)
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 {{ $gridClass }}">
                @foreach($steps as $step)
                    <div class="flex flex-col items-center gap-4 text-center">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary-dark">
                            @if(! empty($step['icon']))
                                {!! \App\Support\Icon::render($step['icon'], 'h-7 w-7') !!}
                            @endif
                        </span>
                        <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $loop->iteration }}. {{ $step['title'] ?? '' }}</h3>
                        @if(! empty($step['description']))
                            <p class="text-sm leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($step['description']) !!}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
