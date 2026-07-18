@php
    use App\Support\Reservations\ClientReservationState;
@endphp

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Moje rezervace</h1>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-line">
        @foreach(['nadchazejici' => 'Nadcházející', 'minule' => 'Minulé'] as $key => $label)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    '-mb-px border-b-2 px-4 py-2.5 font-heading text-sm font-semibold transition',
                    'border-primary text-primary-dark' => $tab === $key,
                    'border-transparent text-neutral-500 hover:text-neutral-800' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if($reservations->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">
                {{ $tab === 'nadchazejici' ? 'Nemáte žádné nadcházející rezervace.' : 'Zatím tu nejsou žádné minulé rezervace.' }}
            </p>
            <p class="mt-2 text-sm text-neutral-500">
                <a href="{{ route('reservation.wizard') }}" class="font-medium text-primary-dark underline">Objednat se online</a>
            </p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($reservations as $reservation)
                @php($state = ClientReservationState::for($reservation))
                <div @class([
                    'flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white px-5 py-4',
                    'border-amber-200 bg-amber-50/40' => $state->isAwaitingPayment(),
                    'border-line' => ! $state->isAwaitingPayment(),
                ])>
                    <div class="min-w-0">
                        <p class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $reservation->service?->name ?? 'Rezervace' }}</p>
                        <p class="mt-0.5 text-xs text-neutral-600">
                            {{ $reservation->startsAt()->translatedFormat('j. n. Y · H:i') }}
                            @if($reservation->therapist?->user?->name) · {{ $reservation->therapist->user->name }} @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>

                        @if($state->isAwaitingPayment())
                            <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full bg-primary px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-primary-dark">Zaplatit</a>
                        @endif

                        <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full border border-line bg-white px-4 py-1.5 font-heading text-xs font-semibold text-neutral-700 transition hover:border-primary hover:text-primary">Detail</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
