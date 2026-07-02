@php
    $config ??= [];
    $cards = $config['cards'] ?? [];
    $buttons = $config['buttons'] ?? [];
    $cols = (int) ($config['columns'] ?? 3);
    $gridClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $sectionBg = ($config['background'] ?? null) === 'alt' ? 'bg-surface-alt' : '';
@endphp

<section class="py-16 lg:py-24 {{ $sectionBg }}">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($cards)
            <div class="grid grid-cols-1 gap-6 {{ $gridClass }}">
                @foreach($cards as $card)
                    <div class="rounded-2xl border border-line bg-white p-8">
                        <div class="flex items-center gap-4">
                            @if(! empty($card['icon']))
                                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                                    {!! \App\Support\Icon::render($card['icon'], 'h-[22px] w-[22px]') !!}
                                </span>
                            @endif
                            <h3 class="font-heading text-xl font-bold text-neutral-900">{{ $card['title'] ?? '' }}</h3>
                        </div>

                        @if(! empty($card['description']))
                            <div class="ff-tick-list mt-6 text-[15px] leading-relaxed text-neutral-800">{!! \App\Support\RichText::inline($card['description']) !!}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($buttons)
                <div class="mt-12 flex flex-wrap justify-center gap-3">
                    @foreach($buttons as $btn)
                        @include('bricks.partials.button', ['btn' => $btn])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>
