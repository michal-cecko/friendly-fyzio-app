@php
    $config ??= [];
    $items = $config['items'] ?? [];
    $star = rescue(fn () => svg('heroicon-s-star', 'h-5 w-5')->toHtml(), '', false);
@endphp

<section class="bg-surface-alt py-16 lg:py-24">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        @include('bricks.partials.heading', ['config' => $config])

        @if($items)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($items as $item)
                    <figure class="flex h-full flex-col rounded-2xl border border-line bg-white p-8">
                        <div class="mb-4 flex gap-0.5 text-primary">
                            @for($i = 0; $i < 5; $i++)
                                {!! $star !!}
                            @endfor
                        </div>
                        <blockquote class="flex-1 leading-relaxed text-neutral-700">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</blockquote>
                        <figcaption class="mt-6">
                            <div class="font-semibold text-neutral-900">{{ $item['author'] ?? '' }}</div>
                            @if(! empty($item['role']))
                                <div class="text-sm text-neutral-500">{{ $item['role'] }}</div>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
