@php
    $config ??= [];
    $cards = $config['cards'] ?? [];
    $cols = (int) ($config['columns'] ?? 3);
    $gridClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<section class="py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($cards)
            <div class="grid grid-cols-1 gap-6 {{ $gridClass }}">
                @foreach($cards as $card)
                    @php($url = \App\Support\LinkResolver::fromConfig($card, ''))
                    <div class="rounded-2xl border border-line bg-white p-8 transition hover:border-primary hover:shadow-lg hover:shadow-primary/5">
                        @if(! empty($card['icon']))
                            <div class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-light text-primary-dark">
                                {!! rescue(fn () => svg($card['icon'], 'h-6 w-6')->toHtml(), '', false) !!}
                            </div>
                        @endif
                        <h3 class="font-heading text-xl font-bold text-neutral-900">{{ $card['title'] ?? '' }}</h3>
                        @if(! empty($card['description']))
                            <p class="mt-3 leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($card['description']) !!}</p>
                        @endif
                        @if($url)
                            <a href="{{ $url }}" class="mt-5 inline-flex items-center gap-1 font-semibold text-primary transition hover:text-primary-dark">
                                Více <span aria-hidden="true">→</span>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
