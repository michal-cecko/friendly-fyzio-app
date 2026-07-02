@php
    $config ??= [];
    $therapists = $config['therapists'] ?? [];
    // Button falls back to legacy button_text/button_url for pages not yet migrated.
    $buttonText = $config['text'] ?? $config['button_text'] ?? null;
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

        @if($therapists)
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach($therapists as $therapist)
                    @php($avatar = \App\Support\Media::url($therapist['avatar'] ?? null, 'thumb'))
                    @php($slots = $therapist['slots'] ?? [])
                    <div class="flex items-center gap-4 rounded-xl border border-line bg-white p-4">
                        <span class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-primary-light">
                            @if($avatar)
                                <img src="{{ $avatar }}" alt="{{ $therapist['name'] ?? '' }}" class="h-full w-full object-cover">
                            @endif
                        </span>
                        <div class="w-36 shrink-0">
                            <div class="font-heading text-sm font-semibold text-neutral-900">{{ $therapist['name'] ?? '' }}</div>
                            @if(! empty($therapist['role']))
                                <div class="text-xs text-neutral-500">{{ $therapist['role'] }}</div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-wrap gap-1.5">
                            @foreach($slots as $slot)
                                <span class="rounded-lg bg-surface-alt px-2.5 py-1.5 text-xs font-medium text-primary-dark">{{ is_array($slot) ? ($slot['label'] ?? '') : $slot }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
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
