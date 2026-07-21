@php
    use App\Support\Reservations\ClientReservationState;
@endphp

<div class="flex flex-col gap-6">
    <div>
        <h1 class="font-heading text-2xl font-bold text-neutral-900">Vítejte zpět, {{ $firstName }}!</h1>
        <p class="mt-1 text-sm text-neutral-500">Tady najdete přehled svých rezervací, náhradních vstupů a kreditu.</p>
    </div>

    {{-- Upcoming reservations --}}
    <div class="rounded-2xl border border-line bg-white p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-heading text-base font-bold text-neutral-900">Nadcházející rezervace</h2>
            <a href="{{ route('zone.reservations') }}" class="text-sm font-medium text-primary-dark underline">Zobrazit vše</a>
        </div>

        @if($upcoming->isEmpty())
            <p class="mt-4 text-sm text-neutral-500">Nemáte žádné nadcházející rezervace. <a href="{{ route('reservation.wizard') }}" class="font-medium text-primary-dark underline">Objednejte se online</a>.</p>
        @else
            <div class="mt-4 flex flex-col gap-3">
                @foreach($upcoming as $reservation)
                    @php($state = ClientReservationState::for($reservation))
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-primary-light/40 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $reservation->service?->name ?? 'Rezervace' }}</p>
                            <p class="mt-0.5 text-xs text-neutral-600">
                                {{ $reservation->startsAt()->translatedFormat('j. n. Y · H:i') }}
                                @if($reservation->therapist?->user?->full_name) · {{ $reservation->therapist->user->full_name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>
                            <a href="{{ route('zone.reservations.show', $reservation) }}" class="rounded-full border border-line bg-white px-4 py-1.5 font-heading text-xs font-semibold text-neutral-700 transition hover:border-primary hover:text-primary">Detail</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Substitute entries --}}
        <div class="rounded-2xl border border-line bg-white p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-base font-bold text-neutral-900">Náhradní vstupy</h2>
                @if($tokens->isNotEmpty())
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ $tokens->count() }} k dispozici</span>
                @endif
            </div>

            @if($tokens->isEmpty())
                <p class="mt-4 text-sm text-neutral-500">Žádné aktivní náhradní vstupy. Vzniknou včasnou omluvou z lekce kurzu.</p>
            @else
                <div class="mt-4 flex flex-col gap-3">
                    @foreach($tokens as $token)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $token->sourceLesson?->series?->course?->name ?? 'Náhradní vstup' }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">Platí do {{ $token->expires_at->format('j. n. Y') }}</p>
                            </div>
                            <a href="{{ route('zone.tokens') }}" class="shrink-0 rounded-full bg-primary px-4 py-1.5 font-heading text-xs font-semibold text-white transition hover:bg-primary-dark">Použít</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Credit --}}
        <div class="rounded-2xl border border-line bg-white p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-base font-bold text-neutral-900">Kredit</h2>
                <a href="{{ route('zone.credits') }}" class="text-sm font-medium text-primary-dark underline">Historie kreditů</a>
            </div>

            <div class="mt-4 rounded-xl bg-primary px-5 py-6 text-center">
                <p class="font-heading text-3xl font-bold text-white">{{ number_format($creditBalance, 0, ',', ' ') }} Kč</p>
                <p class="mt-1 text-xs text-white/80">Aktuální zůstatek</p>
            </div>

            <p class="mt-3 text-center text-xs text-neutral-500">
                @if($creditExpiry)
                    Nejbližší platnost do {{ $creditExpiry->format('j. n. Y') }}
                @elseif($lastCreditChange)
                    Poslední změna {{ $lastCreditChange->format('j. n. Y') }}
                @else
                    Kredit získáte např. převodem dárkového poukazu.
                @endif
            </p>
        </div>
    </div>
</div>
