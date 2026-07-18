<div class="flex flex-col gap-6">
    <a href="{{ route('zone.reservations.show', $reservation) }}" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-primary-dark transition hover:text-primary">
        <x-lucide name="arrow-left" class="h-4 w-4" />
        Zpět na detail rezervace
    </a>

    <div>
        <h1 class="font-heading text-2xl font-bold text-neutral-900">Přesunout termín</h1>
        <p class="mt-1 text-sm text-neutral-500">
            {{ $reservation->service?->name }}
            @if($reservation->therapist?->user?->name) · {{ $reservation->therapist->user->name }} @endif
        </p>
    </div>

    {{-- Current termin --}}
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white px-5 py-4">
        <x-lucide name="calendar" class="h-5 w-5 shrink-0 text-primary" />
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">Současný termín</p>
            <p class="font-heading text-sm font-semibold text-neutral-900">{{ $reservation->startsAt()->translatedFormat('l j. F Y · H:i') }}</p>
        </div>
    </div>

    @if(! $reschedulable)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
            <div class="flex justify-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <x-lucide name="calendar-off" class="h-6 w-6" />
                </span>
            </div>
            <h2 class="mt-4 font-heading text-lg font-bold text-neutral-900">Termín už nelze přesunout</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-neutral-600">
                Termín je za méně než {{ $reservation->cancelBeforeHours() }} hodin (nebo už proběhl), proto ho online přesunout nejde.
                @if($phone) Zavolejte nám prosím na {{ $phone }} a domluvíme se. @else Kontaktujte nás prosím a domluvíme se. @endif
            </p>
            <a href="{{ route('zone.reservations.show', $reservation) }}" class="mt-5 inline-flex rounded-full border-[1.5px] border-line bg-white px-6 py-2.5 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                Zpět na detail
            </a>
        </div>
    @else
        @if($slotTaken)
            <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Tento čas mezitím někdo obsadil. Vyberte prosím jiný.</p>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_18rem]">
            {{-- Calendar --}}
            <div class="rounded-2xl border border-line bg-white p-6" x-data="{ m: {{ $initialMonth }}, total: {{ count($months) }}, labels: @js(array_column($months, 'label')) }">
                <div class="flex items-center justify-between">
                    <button type="button" @click="m = Math.max(0, m - 1)" :disabled="m === 0" class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-600 transition hover:bg-surface-alt disabled:opacity-30" aria-label="Předchozí měsíc">
                        <x-lucide name="chevron-left" class="h-5 w-5" />
                    </button>
                    <span class="font-heading text-base font-semibold text-neutral-900" x-text="labels[m]"></span>
                    <button type="button" @click="m = Math.min(total - 1, m + 1)" :disabled="m === total - 1" class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-600 transition hover:bg-surface-alt disabled:opacity-30" aria-label="Další měsíc">
                        <x-lucide name="chevron-right" class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-7 gap-1">
                    @foreach(['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $weekday)
                        <span class="flex h-10 items-center justify-center text-xs font-semibold text-neutral-500">{{ $weekday }}</span>
                    @endforeach
                </div>

                @foreach($months as $i => $month)
                    <div @if($i !== $initialMonth) x-cloak @endif x-show="m === {{ $i }}" class="grid grid-cols-7 gap-y-1">
                        @foreach($month['weeks'] as $week)
                            @foreach($week as $cell)
                                @if($cell === null)
                                    <span class="h-10"></span>
                                @elseif($cell['available'])
                                    <button
                                        type="button"
                                        wire:key="day-{{ $cell['date'] }}"
                                        wire:click="selectDate('{{ $cell['date'] }}')"
                                        @class([
                                            'relative mx-auto flex h-10 w-10 items-center justify-center rounded-full text-sm transition',
                                            'bg-primary font-semibold text-white' => $date === $cell['date'],
                                            'border-2 border-primary font-semibold text-primary' => $cell['today'] && $date !== $cell['date'],
                                            'font-normal text-neutral-900 hover:bg-primary-light' => ! $cell['today'] && $date !== $cell['date'],
                                        ])
                                    >
                                        {{ $cell['day'] }}
                                        @if($date !== $cell['date'])
                                            <span class="absolute bottom-1 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-primary"></span>
                                        @endif
                                    </button>
                                @elseif($cell['queue'] === 'full')
                                    <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-sm font-semibold text-amber-500" title="Obsazeno">{{ $cell['day'] }}</span>
                                @elseif($cell['today'])
                                    <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary text-sm font-semibold text-primary">{{ $cell['day'] }}</span>
                                @else
                                    <span class="mx-auto flex h-10 w-10 items-center justify-center text-sm text-neutral-400">{{ $cell['day'] }}</span>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                @endforeach

                @if($months !== [] && empty($this->calendarDays['available']))
                    <p class="mt-4 rounded-xl bg-surface-muted px-4 py-3 text-center text-sm text-neutral-500">
                        V nejbližší době nejsou volné termíny.@if($phone) Kontaktujte nás na {{ $phone }}. @endif
                    </p>
                @endif
            </div>

            {{-- Times + confirm --}}
            <div class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-6">
                <h2 class="font-heading text-base font-bold text-neutral-900">Vyberte čas</h2>

                @if(blank($date))
                    <p class="text-sm text-neutral-500">Nejprve zvolte datum v kalendáři.</p>
                @else
                    @php($slots = collect($times)->map->start()->unique()->values())
                    @if($slots->isEmpty())
                        <p class="text-sm text-neutral-500">Pro tento den už nejsou volné časy. Zvolte prosím jiné datum.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach($slots as $time)
                                <button
                                    type="button"
                                    wire:key="time-{{ $time }}"
                                    wire:click="selectTime('{{ $time }}')"
                                    @class([
                                        'rounded-full px-4 py-2 font-heading text-sm font-semibold transition',
                                        'bg-primary text-white' => $startTime === $time,
                                        'border border-line bg-white text-neutral-700 hover:border-primary hover:text-primary' => $startTime !== $time,
                                    ])
                                >{{ $time }}</button>
                            @endforeach
                        </div>
                    @endif
                @endif

                @if(filled($date) && filled($startTime))
                    <div class="mt-auto flex flex-col gap-3 border-t border-line pt-4">
                        <div class="rounded-xl bg-primary-light/50 px-4 py-3 text-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Nový termín</p>
                            <p class="mt-0.5 font-heading font-semibold text-neutral-900">
                                {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('j. F Y') }} · {{ $startTime }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="reschedule"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60"
                        >
                            <x-lucide name="calendar-check" class="h-4 w-4" />
                            Potvrdit přesun
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
