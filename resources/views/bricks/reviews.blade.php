@php
    $config ??= [];
    $reviews ??= collect();
    $bg = ($config['background'] ?? 'alt') === 'white' ? 'bg-white' : 'bg-surface-alt';
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($reviews->isNotEmpty())
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($reviews as $review)
                    @php($avatar = \App\Support\Media::url($review->photo ?? null, 'thumb'))
                    <figure class="flex h-full flex-col gap-4 rounded-2xl border border-line bg-white p-8">
                        <div class="flex gap-0.5 text-primary" aria-label="Hodnocení {{ (int) $review->rating }} z 5">
                            @for($i = 1; $i <= 5; $i++)
                                <x-lucide name="star" class="h-4 w-4 {{ $i <= (int) $review->rating ? 'fill-current' : 'text-neutral-300' }}" />
                            @endfor
                        </div>
                        <blockquote class="flex-1 whitespace-pre-line italic leading-relaxed text-neutral-900">{{ $review->content }}</blockquote>
                        <figcaption class="flex items-center gap-3">
                            @if($avatar)
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-primary-light">
                                    <img src="{{ $avatar }}" alt="{{ $review->author_name }}" class="h-full w-full object-cover">
                                </span>
                            @endif
                            <span class="flex flex-col">
                                <span class="font-heading text-sm font-semibold text-neutral-900">{{ $review->author_name }}</span>
                                @if(filled($review->author_role))
                                    <span class="text-[13px] text-neutral-600">{{ $review->author_role }}</span>
                                @endif
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
