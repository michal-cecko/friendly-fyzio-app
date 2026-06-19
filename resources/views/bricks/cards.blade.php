@php
    $config ??= [];
    $cards = $config['cards'] ?? [];
@endphp

<section class="bg-surface-alt py-16 lg:py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        @include('bricks.partials.heading', ['config' => $config])

        @if($cards)
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($cards as $card)
                    @php($url = \App\Support\LinkResolver::fromConfig($card, ''))
                    @php($image = \App\Support\Media::url($card['image'] ?? null, '400'))
                    <article class="group overflow-hidden rounded-2xl border border-line bg-white">
                        <div class="aspect-[16/10] w-full overflow-hidden bg-primary-light">
                            @if($image)
                                <img src="{{ $image }}" alt="{{ $card['title'] ?? '' }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @endif
                        </div>
                        <div class="p-6">
                            @if(! empty($card['meta']))
                                <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-primary">{{ $card['meta'] }}</p>
                            @endif
                            <h3 class="font-heading text-lg font-bold text-neutral-900">{{ $card['title'] ?? '' }}</h3>
                            @if(! empty($card['description']))
                                <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $card['description'] }}</p>
                            @endif
                            @if($url)
                                <a href="{{ $url }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-dark">
                                    Zobrazit detail <span aria-hidden="true">→</span>
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
