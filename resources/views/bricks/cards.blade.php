@php
    $config ??= [];
    $cards = $config['cards'] ?? [];
    $cols = (int) ($config['columns'] ?? 4);
    $bg = ($config['background'] ?? 'white') === 'alt' ? 'bg-surface-alt' : 'bg-white';
    $grid = match ($cols) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($cards)
            <div class="grid grid-cols-1 gap-6 {{ $grid }}">
                @foreach($cards as $card)
                    @php($url = \App\Support\LinkResolver::fromConfig($card, ''))
                    @php($img = \App\Support\Media::url($card['image'] ?? null, '400'))
                    @php($ctaText = $card['text'] ?? $card['link_text'] ?? 'Zjistit více')
                    @php($cardTag = $url ? 'a' : 'article')
                    <{{ $cardTag }} @if($url) href="{{ $url }}" @endif class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white transition {{ $url ? 'hover:border-primary hover:shadow-lg' : '' }}">
                        <div class="aspect-[16/10] w-full overflow-hidden bg-primary-light">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $card['title'] ?? '' }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-3 p-6">
                            <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $card['title'] ?? '' }}</h3>
                            @if(! empty($card['description']))
                                <p class="text-sm leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($card['description']) !!}</p>
                            @endif
                            @if($url)
                                <span class="mt-auto inline-flex items-center gap-1.5 pt-1 font-heading text-sm font-semibold text-primary transition group-hover:text-primary-dark">
                                    {{ $ctaText }}
                                    {!! \App\Support\Icon::render($card['icon'] ?? 'arrow-right', 'h-4 w-4') !!}
                                </span>
                            @endif
                        </div>
                    </{{ $cardTag }}>
                @endforeach
            </div>
        @endif
    </div>
</section>
