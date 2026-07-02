@php
    $config ??= [];
    $items = $config['items'] ?? [];
    $cardStyle = $config['card_style'] ?? 'warning';
    $cardLeft = ($config['card_position'] ?? 'right') === 'left';
    $bg = ($config['background'] ?? 'white') === 'alt' ? 'bg-surface-alt' : 'bg-white';

    $hasCardHead = ! empty($config['card_icon']) || ! empty($config['card_title']) || ! empty($config['card_note']);

    $card = $cardStyle === 'soft'
        ? ['wrap' => 'bg-surface-alt', 'icon' => 'bg-primary text-white', 'bullet' => 'bg-primary', 'title' => 'text-neutral-900', 'note' => 'font-semibold text-neutral-900', 'item' => 'text-neutral-800', 'rule' => 'bg-line']
        : ['wrap' => 'border border-amber-200 bg-amber-50', 'icon' => 'bg-amber-400 text-white', 'bullet' => 'bg-amber-500', 'title' => 'text-neutral-800', 'note' => 'text-neutral-700', 'item' => 'text-neutral-700', 'rule' => 'bg-amber-500/30'];
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container flex flex-col gap-12 lg:flex-row lg:items-start {{ $cardLeft ? 'lg:flex-row-reverse' : '' }}">
        <div class="flex w-full flex-col gap-5 lg:flex-1">
            @if(! empty($config['icon']))
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
                    {!! \App\Support\Icon::render($config['icon'], 'h-7 w-7') !!}
                </span>
            @endif

            @if(! empty($config['eyebrow']))
                <p class="font-heading text-sm font-semibold uppercase tracking-[0.14em] text-primary">{{ $config['eyebrow'] }}</p>
            @endif

            <h2 class="font-heading text-2xl font-bold text-neutral-900 lg:text-3xl">{!! \App\Support\RichText::inline($config['title'] ?? '') !!}</h2>

            @if(! empty($config['body']))
                <div class="ff-prose text-[15px] leading-relaxed text-neutral-700">{!! $config['body'] !!}</div>
            @endif
        </div>

        <div class="w-full lg:flex-1">
            <div class="flex flex-col gap-5 rounded-2xl p-8 {{ $card['wrap'] }}">
                @if(! empty($config['card_icon']) || ! empty($config['card_title']))
                    <div class="flex items-center gap-4">
                        @if(! empty($config['card_icon']))
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $card['icon'] }}">
                                {!! \App\Support\Icon::render($config['card_icon'], 'h-5 w-5') !!}
                            </span>
                        @endif
                        @if(! empty($config['card_title']))
                            <h3 class="font-heading text-xl font-bold {{ $card['title'] }}">{{ $config['card_title'] }}</h3>
                        @endif
                    </div>
                @endif

                @if(! empty($config['card_note']))
                    <p class="text-sm leading-relaxed {{ $card['note'] }}">{!! \App\Support\RichText::inline($config['card_note']) !!}</p>
                @endif

                @if($hasCardHead && $items)
                    <div class="h-px w-full {{ $card['rule'] }}"></div>
                @endif

                @if($items)
                    <ul class="flex flex-col gap-2.5">
                        @foreach($items as $item)
                            @php($text = is_array($item) ? ($item['text'] ?? '') : $item)
                            @if($text !== '')
                                <li class="flex gap-2.5">
                                    <span class="mt-[0.5em] h-1.5 w-1.5 shrink-0 rounded-full {{ $card['bullet'] }}"></span>
                                    <span class="text-sm leading-relaxed {{ $card['item'] }}">{{ $text }}</span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</section>
