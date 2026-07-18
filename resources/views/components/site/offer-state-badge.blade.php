@props(['state'])

@php
    use App\Enums\OfferState;

    [$classes, $dot] = match ($state) {
        OfferState::Open => ['bg-emerald-50 text-emerald-800', 'bg-emerald-500'],
        OfferState::Full => ['bg-red-50 text-red-700', 'bg-red-500'],
        default => ['bg-neutral-100 text-neutral-500', 'bg-neutral-400'],
    };
@endphp

<span {{ $attributes->class(['inline-flex w-fit items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold', $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $state->label() }}
</span>
