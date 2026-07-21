@php
    use App\Support\Payments\QrPlatba;
    use App\Support\Reservations\ClientReservationState;
    use App\Support\Settings;

    $needsStorno = $reservation->requiresStornoDecision();
    $isActive = ! in_array($state, [ClientReservationState::Cancelled, ClientReservationState::Completed], true);
    $canReschedule = $isActive && ! $reservation->withinStornoWindow();
    $phone = Settings::get('web.contact_phone');
@endphp

{{-- Single stable Livewire root: a conditional root element breaks morphing
     (wire:click silently no-ops), so the confirmation/detail branch lives INSIDE. --}}
<div class="flex flex-col gap-6">
@if($confirmation)
    @php
        $variant = match ($confirmation) {
            'storno_paid' => [
                'icon' => 'circle-check', 'tone' => 'bg-emerald-50 text-emerald-500',
                'title' => 'Rezervace zrušena',
                'desc' => 'Vaše rezervace byla úspěšně zrušena. Pokud byl účtován storno poplatek, najdete ho v sekci Platby.',
                'rows' => [
                    ['Terapie', $reservation->service?->name ?? '—', false],
                    ['Původní termín', $reservation->startsAt()->translatedFormat('j. n. Y · H:i'), false],
                    ['Storno poplatek', number_format($reservation->stornoFee(), 0, ',', ' ').' Kč ('.Settings::stornoFeePercent().' %)', true],
                ],
            ],
            'doctor_note' => [
                'icon' => 'file-text', 'tone' => 'bg-amber-50 text-amber-500',
                'title' => 'Čeká na potvrzení od lékaře',
                'desc' => 'Rezervace byla zrušena. Pošlete prosím potvrzení od lékaře'.($contactEmail ? ' na '.$contactEmail : '').' do 24 hodin. Po doručení vám storno poplatek nebude účtován.',
                'rows' => [
                    ['Terapie', $reservation->service?->name ?? '—', false],
                    ['Původní termín', $reservation->startsAt()->translatedFormat('j. n. Y · H:i'), false],
                    ['Termín dodání potvrzení', 'do 24 hodin', true],
                ],
            ],
            'deactivated' => [
                'icon' => 'user-x', 'tone' => 'bg-red-50 text-red-500',
                'title' => 'Účet byl deaktivován',
                'desc' => 'Rezervace byla zrušena. Protože jste se rozhodli neuhradit storno poplatek, váš účet byl deaktivován a nebude možné pod vaším jménem vytvořit nové rezervace. Pro obnovení účtu nás prosím kontaktujte.',
                'rows' => [
                    ['Terapie', $reservation->service?->name ?? '—', false],
                    ['Původní termín', $reservation->startsAt()->translatedFormat('j. n. Y · H:i'), false],
                    ['Stav účtu', 'Deaktivovaný', true],
                ],
            ],
            default => [
                'icon' => 'circle-check', 'tone' => 'bg-emerald-50 text-emerald-500',
                'title' => 'Rezervace zrušena',
                'desc' => 'Vaše rezervace byla úspěšně zrušena.',
                'rows' => [
                    ['Terapie', $reservation->service?->name ?? '—', false],
                    ['Původní termín', $reservation->startsAt()->translatedFormat('j. n. Y · H:i'), false],
                ],
            ],
        };
    @endphp

    <div class="mx-auto w-full max-w-lg rounded-3xl border border-line bg-white p-8 text-center">
        <div class="flex justify-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full {{ $variant['tone'] }}">
                <x-lucide :name="$variant['icon']" class="h-7 w-7" />
            </span>
        </div>

        <h1 class="mt-5 font-heading text-2xl font-bold text-neutral-900">{{ $variant['title'] }}</h1>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-neutral-500">{{ $variant['desc'] }}</p>

        <dl class="mt-6 flex flex-col gap-2.5 rounded-xl bg-surface-alt px-5 py-4 text-left text-sm">
            @foreach($variant['rows'] as [$label, $value, $highlight])
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-neutral-500">{{ $label }}</dt>
                    <dd @class(['font-heading font-semibold', 'text-red-600' => $highlight, 'text-neutral-900' => ! $highlight])>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-6 flex flex-col gap-2.5">
            @if($confirmation === 'doctor_note')
                <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Potvrzení od lékaře – storno rezervace') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-amber-600">
                    <x-lucide name="mail" class="h-4 w-4" />
                    Odeslat potvrzení e-mailem
                </a>
                <a href="{{ route('zone.dashboard') }}" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">Zpět na přehled</a>
            @elseif($confirmation === 'deactivated')
                <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Obnovení účtu a úhrada storno poplatku') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="credit-card" class="h-4 w-4" />
                    Uhradit a obnovit účet
                </a>
                <a @if($phone) href="tel:{{ preg_replace('/\s+/', '', $phone) }}" @else href="mailto:{{ $contactEmail }}" @endif class="inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                    <x-lucide name="phone" class="h-4 w-4" />
                    Kontaktovat podporu
                </a>
            @else
                <a href="{{ route('zone.dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                    <x-lucide name="layout-dashboard" class="h-4 w-4" />
                    Zpět na přehled
                </a>
                <a href="{{ route('zone.reservations') }}" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">Moje rezervace</a>
            @endif
        </div>
    </div>
@else
    <a href="{{ route('zone.reservations') }}" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-primary-dark transition hover:text-primary">
        <x-lucide name="arrow-left" class="h-4 w-4" />
        Zpět na rezervace
    </a>

    {{-- Heading + actions --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-heading text-2xl font-bold text-neutral-900">{{ $reservation->service?->name ?? 'Rezervace' }}</h1>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">{{ $reservation->startsAt()->translatedFormat('l j. F Y · H:i') }}</p>
        </div>

        @if($isActive)
            <div class="flex flex-wrap gap-2.5">
                @if($canReschedule)
                    <a href="{{ route('zone.reservations.reschedule', $reservation) }}" class="inline-flex items-center gap-1.5 rounded-full bg-primary px-5 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                        <x-lucide name="calendar-sync" class="h-4 w-4" />
                        Přesunout termín
                    </a>
                @else
                    <span
                        x-data
                        @click="$dispatch('open-reschedule-disabled')"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-surface-muted px-5 py-2.5 font-heading text-sm font-semibold text-neutral-400"
                    >
                        <x-lucide name="calendar-off" class="h-4 w-4" />
                        Přesunout termín
                    </span>
                @endif

                <button
                    type="button"
                    wire:click="openCancel"
                    class="inline-flex items-center gap-1.5 rounded-full border-[1.5px] border-red-200 bg-white px-5 py-2.5 font-heading text-sm font-semibold text-red-600 transition hover:bg-red-50"
                >
                    <x-lucide name="x" class="h-4 w-4" />
                    Zrušit rezervaci
                </button>
            </div>
        @endif
    </div>

    {{-- Info card --}}
    <div class="grid grid-cols-1 gap-4 rounded-2xl border border-line bg-white p-6 sm:grid-cols-3">
        <div class="flex flex-col gap-1 border-l-2 border-primary pl-4">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">Datum a čas</span>
            <span class="font-heading text-sm font-semibold text-neutral-900">{{ $reservation->startsAt()->translatedFormat('j. F Y') }}</span>
            <span class="text-sm text-neutral-600">{{ $reservation->startsAt()->format('H:i') }} – {{ $reservation->endsAt()->format('H:i') }}</span>
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">Terapeut</span>
            <span class="font-heading text-sm font-semibold text-neutral-900">{{ $reservation->therapist?->user?->full_name ?? '—' }}</span>
            @if($reservation->room?->name)
                <span class="inline-flex items-center gap-1.5 text-sm text-neutral-600">
                    <x-lucide name="map-pin" class="h-3.5 w-3.5 shrink-0 text-primary" />
                    {{ $reservation->room->name }}
                </span>
            @endif
        </div>

        <div class="flex flex-col gap-1 sm:text-right">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">Cena</span>
            <span class="font-heading text-lg font-bold text-primary">{{ number_format((int) ($reservation->service?->price ?? 0), 0, ',', ' ') }} Kč</span>
            <span class="text-sm text-neutral-600">{{ $state->isAwaitingPayment() ? 'K úhradě' : 'Za terapii' }}</span>
        </div>
    </div>

    {{-- Payment panel (unpaid QR request: late-storno fee or a requested payment) --}}
    @if($openQrPayment)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6">
            <h2 class="font-heading text-base font-bold text-neutral-900">Způsob platby</h2>
            <p class="mt-1 text-sm text-neutral-600">Naskenujte QR kód ve své bankovní aplikaci, nebo zadejte platbu ručně.</p>

            <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-[1fr_auto]">
                <dl class="grid grid-cols-1 gap-2.5 text-sm">
                    <div class="flex justify-between gap-4 border-b border-amber-200/70 pb-2">
                        <dt class="text-neutral-500">Číslo účtu (IBAN)</dt>
                        <dd class="font-heading font-semibold text-neutral-900">{{ Settings::iban() ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-amber-200/70 pb-2">
                        <dt class="text-neutral-500">Variabilní symbol</dt>
                        <dd class="font-heading font-semibold text-neutral-900">{{ $openQrPayment->variable_symbol }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-amber-200/70 pb-2">
                        <dt class="text-neutral-500">Částka</dt>
                        <dd class="font-heading font-semibold text-primary">{{ number_format((int) $openQrPayment->amount, 0, ',', ' ') }} Kč</dd>
                    </div>
                    @if($openQrPayment->due_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-neutral-500">Splatnost</dt>
                            <dd class="font-heading font-semibold text-neutral-900">{{ $openQrPayment->due_at->format('j. n. Y') }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="flex items-center justify-center rounded-xl bg-white p-3">
                    <img src="{{ QrPlatba::dataUri($openQrPayment) }}" alt="QR platba" class="h-40 w-40">
                </div>
            </div>
        </div>
    @endif

    {{-- Visit preparation --}}
    @if($isActive)
        <div class="rounded-2xl border border-line bg-white p-6">
            <h2 class="font-heading text-base font-bold text-neutral-900">Příprava na návštěvu</h2>
            <ul class="mt-4 flex flex-col gap-2.5">
                @foreach([
                    'Vezměte si pohodlné sportovní oblečení.',
                    'Přijďte prosím 5–10 minut před termínem.',
                    'Máte-li lékařské zprávy nebo snímky, vezměte je s sebou.',
                ] as $item)
                    <li class="flex items-start gap-2.5 text-sm text-neutral-600">
                        <x-lucide name="circle-check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Cancellation terms --}}
    <div class="rounded-2xl border border-line bg-surface-alt p-6">
        <h2 class="font-heading text-base font-bold text-neutral-900">Storno podmínky</h2>
        <ul class="mt-4 flex flex-col gap-2.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2.5">
                <x-lucide name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                Zdarma zrušíte do {{ $reservation->cancelBeforeHours() }} hodin před termínem.
            </li>
            <li class="flex items-start gap-2.5">
                <x-lucide name="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                Při pozdějším zrušení účtujeme storno poplatek {{ Settings::stornoFeePercent() }} % z ceny ({{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč). Omluva ze zdravotních důvodů s potvrzením od lékaře je bez poplatku.
            </li>
            @if($phone)
                <li class="flex items-start gap-2.5">
                    <x-lucide name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    Po uplynutí lhůty nás prosím kontaktujte na {{ $phone }}.
                </li>
            @endif
        </ul>
    </div>

    {{-- Reschedule-disabled modal --}}
    <div
        x-data="{ open: false }"
        x-on:open-reschedule-disabled.window="open = true"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-xl" @keydown.escape.window="open = false">
            <div class="flex justify-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                    <x-lucide name="calendar-off" class="h-7 w-7" />
                </span>
            </div>
            <h2 class="mt-5 font-heading text-xl font-bold text-neutral-900">Termín už nelze přesunout</h2>
            <p class="mt-2 text-sm leading-relaxed text-neutral-500">
                Termín je za méně než {{ $reservation->cancelBeforeHours() }} hodin, proto ho online přesunout nejde.
                @if($phone) Zavolejte nám prosím na {{ $phone }} a domluvíme se. @else Kontaktujte nás prosím a domluvíme se. @endif
            </p>
            <button type="button" @click="open = false" class="mt-6 w-full rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                Zavřít
            </button>
        </div>
    </div>

    {{-- Cancel modals --}}
    @if($confirmingCancel)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closeCancel"></div>

            <div class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
                @if($needsStorno)
                    {{-- Late cancellation: the storno decision --}}
                    <div class="flex justify-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-500">
                            <x-lucide name="coins" class="h-7 w-7" />
                        </span>
                    </div>
                    <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Vyberte způsob zrušení</h2>
                    <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                        @if($reservation->withinStornoWindow())
                            Termín je za méně než {{ $reservation->cancelBeforeHours() }} hodin. Vyberte, jak chcete pokračovat se zrušením rezervace.
                        @else
                            Tato rezervace je potvrzená, proto se na její zrušení vztahují storno podmínky. Vyberte, jak chcete pokračovat.
                        @endif
                    </p>

                    <div class="mt-5 rounded-xl border border-red-100 bg-red-50 p-4">
                        <p class="flex items-center gap-2 font-heading text-sm font-semibold text-red-700">
                            <x-lucide name="triangle-alert" class="h-4 w-4 shrink-0" />
                            Storno poplatek
                        </p>
                        <p class="mt-1.5 text-xs leading-relaxed text-red-700/80">
                            Podle storno podmínek vám bude účtovaný poplatek za neskoré zrušení rezervace.
                        </p>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-4 rounded-xl bg-surface-alt px-4 py-3">
                        <span class="text-sm text-neutral-500">Storno poplatek:</span>
                        <span class="font-heading text-base font-bold text-red-600">{{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč ({{ \App\Support\Settings::stornoFeePercent() }} %)</span>
                    </div>

                    <div class="mt-5 flex flex-col gap-2.5">
                        <button type="button" wire:click="cancelAndPay" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                            <x-lucide name="check" class="h-4 w-4" />
                            Nepřijdu, zaplatím storno
                        </button>
                        <button type="button" wire:click="cancelWithDoctorNote" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60">
                            <x-lucide name="file-text" class="h-4 w-4" />
                            Nepřijdu, dodám potvrzení od lékaře
                        </button>
                        <div x-data="{ confirmDeactivate: false }" class="contents">
                            <button type="button" x-show="!confirmDeactivate" @click="confirmDeactivate = true" class="inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] border-red-200 bg-white px-6 py-3 font-heading text-sm font-semibold text-red-600 transition hover:bg-red-50">
                                <x-lucide name="user-x" class="h-4 w-4" />
                                Nepřijdu, nezaplatím (deaktivace účtu)
                            </button>
                            <div x-show="confirmDeactivate" x-cloak class="rounded-xl border-[1.5px] border-red-200 bg-red-50 p-4 text-left">
                                <p class="text-xs leading-relaxed text-red-700">
                                    Opravdu? Váš účet bude <strong>deaktivován</strong> — nebudete se moci přihlásit ani online spravovat své rezervace. Pro obnovení účtu nás budete muset kontaktovat.
                                </p>
                                <div class="mt-3 flex flex-col gap-2">
                                    <button type="button" wire:click="cancelAndDeactivate" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                                        Ano, deaktivovat účet
                                    </button>
                                    <button type="button" @click="confirmDeactivate = false" class="rounded-full border-[1.5px] border-line bg-white px-6 py-2.5 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                                        Zpět
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" wire:click="closeCancel" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            Zpět
                        </button>
                    </div>
                @else
                    {{-- Free cancellation --}}
                    <div class="flex justify-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                            <x-lucide name="triangle-alert" class="h-7 w-7" />
                        </span>
                    </div>
                    <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Zrušit rezervaci?</h2>
                    <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                        Opravdu chcete termín úplně zrušit? Nebo ho chcete jen přeplánovat?
                    </p>

                    <div class="mt-6 flex flex-col gap-2.5">
                        @if($canReschedule)
                            <a href="{{ route('zone.reservations.reschedule', $reservation) }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-primary-dark">
                                <x-lucide name="calendar-sync" class="h-4 w-4" />
                                Přeplánovat termín
                            </a>
                        @endif
                        <button type="button" wire:click="cancelFree" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                            <x-lucide name="x" class="h-4 w-4" />
                            Ano, zrušit rezervaci
                        </button>
                        <button type="button" wire:click="closeCancel" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                            Ponechat rezervaci
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endif
</div>
