@php
    $config ??= [];
    $openings ??= [];
    // Button falls back to legacy button_text/button_url for pages not yet migrated.
    $buttonText = $config['text'] ?? $config['button_text'] ?? null;
    $emptyText = $config['empty_text'] ?? 'Momentálně nejsou volné žádné last-minute termíny. Zkuste to prosím později.';
@endphp

<section class="bg-white py-16 lg:py-20">
    <div class="ff-container">
        <div class="mb-10 flex flex-col items-center gap-1 text-center">
            @if(! empty($config['eyebrow']))
                <p class="font-heading text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ $config['eyebrow'] }}</p>
            @endif
            @if(! empty($config['title']))
                <h2 class="font-heading text-2xl font-bold text-neutral-900">{{ $config['title'] }}</h2>
            @endif
        </div>

        @if(! empty($openings))
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($openings as $opening)
                    @php
                        $photo = \App\Support\Media::url($opening['photo'] ?? null, 'thumb');
                        $permalink = $opening['permalink'] ?? null;
                    @endphp

                    <div class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-5">
                        {{-- Identity — links to the therapist's public profile when published --}}
                        <{{ $permalink ? 'a' : 'div' }} @if($permalink) href="{{ $permalink }}" @endif class="group flex items-center gap-4 {{ $permalink ? 'transition hover:opacity-90' : '' }}">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-light font-heading text-base font-semibold text-primary-dark">
                                @if($photo)
                                    <img src="{{ $photo }}" alt="{{ $opening['name'] }}" class="h-full w-full object-cover">
                                @else
                                    {{ $opening['initials'] }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="font-heading text-sm font-semibold text-neutral-900 {{ $permalink ? 'group-hover:text-primary' : '' }}">{{ $opening['name'] }}</div>
                                @if(! empty($opening['title']))
                                    <div class="text-xs text-neutral-500">{{ $opening['title'] }}</div>
                                @endif
                            </div>
                        </{{ $permalink ? 'a' : 'div' }}>

                        {{-- Bookable services offered by this therapist --}}
                        @if(! empty($opening['services']))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($opening['services'] as $serviceName)
                                    <span class="rounded-full bg-primary-light px-2.5 py-1 text-xs font-medium text-primary-dark">{{ $serviceName }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Real free slots today/tomorrow, each deep-linked into the wizard --}}
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($opening['days'] as $day)
                                @php($dayDate = \Illuminate\Support\Carbon::parse($day['date']))
                                @php($dayLabel = $dayDate->isToday() ? 'Dnes' : ($dayDate->isTomorrow() ? 'Zítra' : $dayDate->translatedFormat('j. n.')))
                                @foreach($day['times'] as $time)
                                    <a href="{{ route('reservation.wizard', ['terapeut' => $opening['slug'], 'datum' => $day['date'], 'cas' => $time]) }}"
                                       class="rounded-lg border border-line bg-surface-alt px-2.5 py-1.5 text-xs font-medium text-primary-dark transition hover:border-primary hover:text-primary">
                                        {{ $dayLabel }} {{ $time }}
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mx-auto max-w-xl text-center text-sm text-neutral-500">{{ $emptyText }}</p>
        @endif

        @if($buttonText)
            <div class="mt-8 flex justify-center">
                @include('bricks.partials.button', ['btn' => [
                    'text' => $buttonText,
                    'style' => $config['style'] ?? 'outline',
                    'color' => $config['color'] ?? null,
                    'icon' => $config['icon'] ?? 'calendar',
                    'link_type' => $config['link_type'] ?? null,
                    'page_id' => $config['page_id'] ?? null,
                    'url' => $config['url'] ?? $config['button_url'] ?? null,
                ]])
            </div>
        @endif
    </div>
</section>
