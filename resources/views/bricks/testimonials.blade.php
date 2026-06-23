@php
    $config ??= [];
    $items = $config['items'] ?? [];
    $bg = ($config['background'] ?? 'alt') === 'white' ? 'bg-white' : 'bg-surface-alt';
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($items)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($items as $item)
                    @php($avatar = \App\Support\Media::url($item['avatar'] ?? null, 'thumb'))
                    <figure class="flex h-full flex-col gap-4 rounded-2xl border border-line bg-white p-8">
                        <div class="font-heading text-5xl leading-[0.5] text-primary" aria-hidden="true">&ldquo;</div>
                        <blockquote class="flex-1 italic leading-relaxed text-neutral-900">{!! \App\Support\RichText::inline($item['quote'] ?? '') !!}</blockquote>
                        <figcaption class="flex items-center gap-3">
                            <span class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-primary-light">
                                @if($avatar)
                                    <img src="{{ $avatar }}" alt="{{ $item['author'] ?? '' }}" class="h-full w-full object-cover">
                                @endif
                            </span>
                            <span class="flex flex-col">
                                <span class="font-heading text-sm font-semibold text-neutral-900">{{ $item['author'] ?? '' }}</span>
                                @if(! empty($item['role']))
                                    <span class="text-[13px] text-neutral-600">{{ $item['role'] }}</span>
                                @endif
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
