@props([
    'reservation',
    'attention' => false,
])

@php
    use App\Support\Reservations\ClientReservationState;

    $state = ClientReservationState::for($reservation);
@endphp

{{-- One row of "Moje rezervace". `attention` renders it inside the highlighted
     group, where the amber border would double up with the group's own. --}}
<div @class([
    'flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white px-5 py-4',
    'border-amber-200' => ! $attention && $state->needsAttention(),
    'border-line' => $attention || ! $state->needsAttention(),
])>
    <div class="min-w-0">
        <p class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $reservation->service?->name ?? 'Rezervace' }}</p>
        <p class="mt-0.5 text-xs text-neutral-600">
            {{ $reservation->startsAt()->translatedFormat('j. n. Y · H:i') }}
            @if($reservation->therapist?->user?->full_name) · {{ $reservation->therapist->user->full_name }} @endif
        </p>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2.5">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>

        @if($state === ClientReservationState::AwaitingDoctorNote)
            <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full bg-amber-500 px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-amber-600">Nahrát potvrzení</a>
        @elseif($state->isAwaitingPayment())
            <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full bg-primary px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-primary-dark">Zaplatit</a>
        @endif

        <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full border border-line bg-white px-4 py-1.5 font-heading text-xs font-semibold text-neutral-700 transition hover:border-primary hover:text-primary">Detail</a>
    </div>
</div>
