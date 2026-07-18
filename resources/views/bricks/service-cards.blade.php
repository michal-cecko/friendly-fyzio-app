@php
    $config ??= [];
    $categories ??= collect();
    $cols = (int) ($config['columns'] ?? 4);
    $bg = ($config['background'] ?? 'white') === 'alt' ? 'bg-surface-alt' : 'bg-white';
    $linkText = $config['link_text'] ?? 'Zjistit více';
    $grid = match ($cols) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($categories->isNotEmpty())
            <div class="grid grid-cols-1 gap-6 {{ $grid }}">
                @foreach($categories as $category)
                    @php($img = \App\Support\Media::url($category->hero_image, '400'))
                    <a href="{{ $category->permalink }}" class="group flex h-full flex-col overflow-hidden rounded-2xl border border-line bg-white transition hover:border-primary hover:shadow-lg hover:shadow-primary/5">
                        <div class="aspect-[16/10] w-full overflow-hidden bg-primary-light">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $category->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col gap-3 p-6">
                            <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $category->name }}</h3>
                            @if(! empty($category->description))
                                <p class="text-sm leading-relaxed text-neutral-600">{!! \App\Support\RichText::inline($category->description) !!}</p>
                            @endif
                            <span class="mt-auto inline-flex items-center gap-1.5 pt-1 font-heading text-sm font-semibold text-primary transition group-hover:gap-2.5"
                                  @if(! empty($config['link_color'])) style="color: {{ $config['link_color'] }}" @endif>
                                {{ $linkText }}
                                {!! \App\Support\Icon::render($config['link_icon'] ?? 'arrow-right', 'h-4 w-4') !!}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
