@props([
    'url' => null,
    'image' => null,
    'imageAlt' => '',
    'category' => null,
    'state' => null,
    'title' => '',
    'subtitle' => null,
    'description' => null,
    'date' => null,
    'taken' => 0,
    'capacity' => 0,
    'price' => null,
    'ctaLabel' => 'Přihlásit se',
    'muted' => false,
    'soonest' => false,
])

@php
    use App\Enums\OfferState;

    // Never surface an overbooked count publicly (e.g. admin-added extras): cap
    // the shown "taken" at capacity so the badge reads 4/4, not 5/4.
    $takenShown = min((int) $taken, (int) $capacity);
    $spotsLeft = max(0, (int) $capacity - (int) $taken);
    $percent = $capacity > 0 ? min(100, (int) round($taken / $capacity * 100)) : 0;
    $isFull = $state === OfferState::Full;
    $isOpen = $state === OfferState::Open;
    $lastSpots = ! $isFull && $spotsLeft <= 3;

    $spotsLabel = match (true) {
        $isFull => 'Obsazeno',
        $spotsLeft === 1 => 'Poslední místo!',
        $lastSpots => "Poslední {$spotsLeft} místa!",
        $spotsLeft <= 4 => "Zbývají {$spotsLeft} místa",
        default => "Zbývá {$spotsLeft} míst",
    };

    $tone = match (true) {
        $isFull => ['bar' => 'bg-red-500', 'text' => 'text-red-600', 'badge' => 'bg-red-50 text-red-700', 'dot' => 'bg-red-500'],
        $lastSpots => ['bar' => 'bg-amber-500', 'text' => 'text-amber-600', 'badge' => 'bg-emerald-50 text-emerald-800', 'dot' => 'bg-emerald-500'],
        default => ['bar' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-50 text-emerald-800', 'dot' => 'bg-emerald-500'],
    };

    if (! $isOpen && ! $isFull) {
        $tone['badge'] = 'bg-neutral-100 text-neutral-500';
        $tone['dot'] = 'bg-neutral-400';
    }
@endphp

<article @class([
    'group flex h-full flex-col overflow-hidden rounded-2xl border bg-white transition hover:shadow-md',
    'border-line' => ! $soonest,
    'border-primary shadow-md ring-1 ring-primary/20' => $soonest,
    'opacity-60 saturate-50' => $muted,
])>
    <a href="{{ $url ?? '#' }}" class="relative block h-45 shrink-0 overflow-hidden bg-primary-light">
        @if($image)
            <img src="{{ $image }}" alt="{{ $imageAlt ?: $title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
        @endif
        @if($category)
            <span class="absolute left-4 top-4 rounded-full bg-primary px-3.5 py-1 font-heading text-xs font-semibold text-white">{{ $category }}</span>
        @endif
        @if($soonest)
            <span class="absolute right-4 top-4 rounded-full bg-white/95 px-3.5 py-1 font-heading text-xs font-semibold text-primary-dark shadow-sm">Nejbližší termín</span>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-3.5 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-0.5">
                <h3 class="font-heading text-lg font-semibold text-neutral-900">
                    <a href="{{ $url ?? '#' }}" class="transition hover:text-primary">{{ $title }}</a>
                </h3>
                @if($subtitle)
                    <p class="truncate text-[13px] font-medium text-neutral-500">{{ $subtitle }}</p>
                @endif
            </div>
            @if($state)
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $tone['badge'] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $tone['dot'] }}"></span>
                    {{ $state->label() }}
                </span>
            @endif
        </div>

        @if($description)
            <p class="line-clamp-2 text-sm leading-relaxed text-neutral-600">{{ $description }}</p>
        @endif

        @if($capacity > 0 && ($isOpen || $isFull))
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium {{ $isFull ? 'text-red-600' : ($lastSpots ? 'text-amber-600' : 'text-emerald-700') }}">{{ $spotsLabel }}</span>
                <span @class(['font-heading text-xs font-semibold', 'text-red-600' => $isFull, 'text-neutral-500' => ! $isFull])>{{ $takenShown }}/{{ $capacity }}</span>
            </div>
        @endif

        <div class="mt-auto flex flex-col gap-4">
            @if($capacity > 0 && ($isOpen || $isFull))
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                    <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $percent }}%"></div>
                </div>
            @else
                <div class="h-px w-full bg-line"></div>
            @endif

            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 flex-col">
                    @if($date)
                        <span class="inline-flex items-center gap-1.5 text-sm text-neutral-600">
                            <x-lucide name="calendar" class="h-4 w-4 shrink-0 text-primary" />
                            <span class="truncate">{{ $date }}</span>
                        </span>
                    @endif
                    @if($price !== null)
                        <span class="font-heading text-sm font-semibold text-neutral-900">{{ $price }}</span>
                    @endif
                </div>

                @if($url)
                    @if($isFull)
                        <a href="{{ $url }}#prihlaseni" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border-[1.5px] border-primary bg-white px-4 py-2 font-heading text-[13px] font-semibold text-primary transition hover:bg-primary-light">
                            <x-lucide name="list" class="h-3.5 w-3.5" />
                            Čekací listina
                        </a>
                    @elseif($isOpen)
                        <a href="{{ $url }}#prihlaseni" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary px-4 py-2 font-heading text-[13px] font-semibold text-white transition hover:bg-primary-dark">
                            {{ $ctaLabel }}
                            <x-lucide name="arrow-right" class="h-3.5 w-3.5" />
                        </a>
                    @else
                        <a href="{{ $url }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-surface-muted px-4 py-2 font-heading text-[13px] font-semibold text-neutral-500 transition hover:bg-neutral-200">
                            Detail
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>
</article>
