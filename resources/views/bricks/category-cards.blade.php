@php
    $config ??= [];
    $categories = $config['categories'] ?? [];
    $buttonUrl = ($config['button_url'] ?? '') ?: '#';
@endphp

<section class="bg-surface-alt py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($categories)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @foreach($categories as $category)
                    @php($url = ($category['url'] ?? '') ?: '#')
                    @php($items = $category['items'] ?? [])
                    <a href="{{ $url }}" class="group flex flex-col gap-4 rounded-2xl border border-line bg-white p-6 transition hover:shadow-lg hover:shadow-primary/5">
                        <div class="flex items-center gap-5">
                            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-light text-primary-dark">
                                <x-lucide :name="$category['icon'] ?? 'activity'" class="h-6 w-6" />
                            </span>
                            <div class="flex-1">
                                <h3 class="font-heading text-lg font-semibold text-neutral-900">{{ $category['title'] ?? '' }}</h3>
                                @if(! empty($category['subtitle']))
                                    <p class="text-sm text-neutral-500">{{ $category['subtitle'] }}</p>
                                @endif
                            </div>
                            <x-lucide name="chevron-right" class="h-5 w-5 text-primary transition group-hover:translate-x-1" />
                        </div>
                        <div class="h-px w-full bg-line"></div>
                        <ul class="flex flex-col gap-2">
                            @foreach($items as $i => $item)
                                <li class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-neutral-700 {{ $i === 0 ? 'bg-surface-alt' : '' }}">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                                    {{ is_array($item) ? ($item['label'] ?? '') : $item }}
                                </li>
                            @endforeach
                        </ul>
                    </a>
                @endforeach
            </div>
        @endif

        @if(! empty($config['button_text']))
            <div class="mt-12 flex justify-center">
                <a href="{{ $buttonUrl }}" class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-primary bg-white px-7 py-3 font-heading text-[15px] font-semibold text-primary transition hover:bg-primary-light">
                    {{ $config['button_text'] }}
                    <x-lucide name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        @endif
    </div>
</section>
