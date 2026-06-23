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
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 {{ $gridClass }}">
                @foreach($steps as $step)
                    <div class="flex flex-col gap-3 rounded-2xl border border-line bg-white p-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-lg font-bold text-white">{{ $loop->iteration }}</span>
                            @if(! empty($step['icon']))
                                <span class="text-primary-dark">{!! rescue(fn () => svg($step['icon'], 'h-6 w-6')->toHtml(), '', false) !!}</span>
                            @endif
                        </div>
                        <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $step['title'] ?? '' }}</h3>
                        @if(! empty($step['description']))
                            <p class="text-sm leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($step['description']) !!}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
