@php
    use App\Livewire\Zone\Reservations;
@endphp

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Moje rezervace</h1>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-line">
        @foreach(Reservations::TABS as $key => $label)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    '-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 font-heading text-sm font-semibold transition',
                    'border-primary text-primary-dark' => $tab === $key,
                    'border-transparent text-neutral-500 hover:text-neutral-800' => $tab !== $key,
                ])
            >
                {{ $label }}
                @if($key === 'aktivni' && $attention->isNotEmpty())
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-bold text-amber-700">{{ $attention->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @if($attention->isEmpty() && $reservations->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">
                {{ $tab === 'aktivni' ? 'Nemáte žádné aktivní rezervace.' : 'Zatím tu nejsou žádné dokončené rezervace.' }}
            </p>
            <p class="mt-2 text-sm text-neutral-500">
                <a href="{{ route('reservation.wizard') }}" class="font-medium text-primary-dark underline">Objednat se online</a>
            </p>
        </div>
    @else
        {{-- Anything the client still has to resolve — an unpaid fee or a promised
             doctor's note — is pulled to the top so it can't be missed. --}}
        @if($attention->isNotEmpty())
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                <p class="flex items-center gap-2 font-heading text-sm font-semibold text-amber-800">
                    <x-lucide name="triangle-alert" class="h-4 w-4 shrink-0" />
                    Vyžaduje vaši pozornost
                </p>

                @foreach($attention as $reservation)
                    <x-zone.reservation-row :reservation="$reservation" attention />
                @endforeach
            </div>
        @endif

        @if($reservations->isNotEmpty())
            <div class="flex flex-col gap-3">
                @foreach($reservations as $reservation)
                    <x-zone.reservation-row :reservation="$reservation" />
                @endforeach
            </div>
        @endif
    @endif
</div>
