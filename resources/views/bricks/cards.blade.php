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
                    <article class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white">
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
                                <div class="mt-auto pt-1">
                                    @include('bricks.partials.button', ['btn' => [
                                        'text' => $card['text'] ?? $card['link_text'] ?? 'Zjistit více',
                                        'style' => $card['style'] ?? 'text',
                                        'color' => $card['color'] ?? null,
                                        'icon' => $card['icon'] ?? 'arrow-right',
                                        'link_type' => $card['link_type'] ?? null,
                                        'page_id' => $card['page_id'] ?? null,
                                        'url' => $card['url'] ?? null,
                                    ]])
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
