@php
    $config ??= [];
    // Category cards come live from App\Support\Enrollments\EnrollingNow; each row
    // already carries a resolved label, meta and detail-page url.
    $categories ??= [];
    $bottomText = $config['text'] ?? $config['button_text'] ?? null;
@endphp

<section class="bg-surface-alt py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($categories)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @foreach($categories as $category)
                    <div class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-6 transition hover:shadow-lg hover:shadow-primary/5">
                        <a href="{{ $category['url'] }}" class="group/head flex items-center gap-5">
                            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-light text-primary-dark">
                                {!! \App\Support\Icon::render($category['icon'] ?? 'graduation-cap', 'h-6 w-6') !!}
                            </span>
                            <div class="flex-1">
                                <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $category['title'] ?? '' }}</h3>
                                @if(! empty($category['subtitle']))
                                    <p class="text-sm text-neutral-500">{{ $category['subtitle'] }}</p>
                                @endif
                            </div>
                            <x-lucide name="chevron-right" class="h-5 w-5 text-primary transition group-hover/head:translate-x-1" />
                        </a>
                        <div class="h-px w-full bg-line"></div>
                        <ul class="flex flex-col gap-1">
                            @foreach($category['items'] ?? [] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" class="group/row flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-neutral-700 transition hover:bg-surface-alt">
                                        <span class="relative flex h-2 w-2 shrink-0">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                                        </span>
                                        <span class="flex min-w-0 flex-1 flex-col">
                                            <span class="truncate font-medium text-neutral-800">{{ $item['label'] }}</span>
                                            @if(! empty($item['meta']))
                                                <span class="truncate text-xs text-neutral-500">{{ $item['meta'] }}</span>
                                            @endif
                                        </span>
                                        <x-lucide name="chevron-right" class="ml-auto h-4 w-4 shrink-0 text-neutral-300 transition group-hover/row:translate-x-0.5 group-hover/row:text-primary" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif

        @if($bottomText)
            <div class="mt-12 flex justify-center">
                @include('bricks.partials.button', ['btn' => [
                    'text' => $bottomText,
                    'style' => $config['style'] ?? 'outline',
                    'color' => $config['color'] ?? null,
                    'icon' => $config['icon'] ?? 'arrow-right',
                    'link_type' => $config['link_type'] ?? null,
                    'link_ref' => $config['link_ref'] ?? null,
                    'page_id' => $config['page_id'] ?? null,
                    'url' => $config['url'] ?? $config['button_url'] ?? null,
                ]])
            </div>
        @endif
    </div>
</section>
