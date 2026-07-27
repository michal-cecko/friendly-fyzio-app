@php
    use App\Enums\ReservationDocumentType;
    use App\Enums\ReservationStatus;
    use App\Support\Payments\QrPlatba;
    use App\Support\Reservations\ClientReservationState;
    use App\Support\Settings;

    $needsStorno = $reservation->requiresStornoDecision();
    // Keyed on the stored status, not the display state: a cancellation has several
    // customer-facing states (unpaid fee, doctor's note pending) and none of them
    // may hand back the confirm / reschedule / cancel actions.
    $isActive = $reservation->status !== ReservationStatus::Cancelled
        && $state !== ClientReservationState::Completed;
    $canReschedule = $isActive && ! $reservation->withinStornoWindow();
    $canChangeStorno = $reservation->canChangeStornoResolution();
    // Pending is only ever reached by a future, non-cancelled reservation.
    $canConfirm = $state === ClientReservationState::Pending;
    $phone = Settings::get('web.contact_phone');
    $actionItem = 'flex w-full items-center gap-2.5 whitespace-nowrap px-4 py-2.5 text-left text-sm font-medium transition hover:bg-surface-alt';
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
                {{-- Dismisses the result screen and reveals the detail page, whose
                     doctor-note card is where the file actually goes. --}}
                <button type="button" wire:click="showDetail" class="inline-flex items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-amber-600">
                    <x-lucide name="upload" class="h-4 w-4" />
                    Nahrát potvrzení
                </button>
                @if($contactEmail)
                    <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Potvrzení od lékaře – storno rezervace') }}" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">Poslat e-mailem na {{ $contactEmail }}</a>
                @endif
                <a href="{{ route('zone.dashboard') }}" class="text-sm font-medium text-neutral-500 underline-offset-2 transition hover:text-neutral-900 hover:underline">Zpět na přehled</a>
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
            @if($reservation->confirmed_at)
                <p class="mt-1 inline-flex items-center gap-1.5 text-sm text-emerald-700">
                    <x-lucide name="circle-check" class="h-3.5 w-3.5 shrink-0" />
                    Potvrzeno {{ $reservation->confirmed_at->translatedFormat('j. n. Y · H:i') }}
                </p>
            @endif
        </div>

        @if($isActive)
            {{-- Three actions no longer fit as inline buttons, so they live in one menu. --}}
            <div
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                class="relative"
            >
                <button
                    type="button"
                    @click="open = ! open"
                    :aria-expanded="open"
                    aria-haspopup="true"
                    class="inline-flex items-center gap-1.5 rounded-full border-[1.5px] border-line bg-white px-5 py-2.5 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt"
                >
                    <x-lucide name="ellipsis" class="h-4 w-4" />
                    Akce
                    {{-- The rotation rides a wrapper: x-lucide forwards only `class`
                         to blade-icons, so x-bind:class on it would be dropped. --}}
                    <span class="text-neutral-400 transition" x-bind:class="open && 'rotate-180'">
                        <x-lucide name="chevron-down" class="h-4 w-4" />
                    </span>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    {{-- w-max so the longest label sets the width; items never wrap. --}}
                    class="absolute right-0 z-20 mt-2 w-max min-w-64 overflow-hidden rounded-2xl border border-line bg-white py-1.5 shadow-lg"
                >
                    @if($canConfirm)
                        <button type="button" wire:click="openConfirm" @click="open = false" class="{{ $actionItem }} text-emerald-700">
                            <x-lucide name="circle-check" class="h-4 w-4 shrink-0" />
                            Potvrdit rezervaci
                        </button>
                    @endif

                    @if($canReschedule)
                        <a href="{{ route('zone.reservations.reschedule', $reservation) }}" class="{{ $actionItem }} text-neutral-900">
                            <x-lucide name="calendar-sync" class="h-4 w-4 shrink-0" />
                            Přesunout termín
                        </a>
                    @else
                        <button type="button" @click="open = false; $dispatch('open-reschedule-disabled')" class="{{ $actionItem }} text-neutral-400">
                            <x-lucide name="calendar-off" class="h-4 w-4 shrink-0" />
                            Přesunout termín
                        </button>
                    @endif

                    <button type="button" wire:click="openCancel" @click="open = false" class="{{ $actionItem }} text-red-600">
                        <x-lucide name="x" class="h-4 w-4 shrink-0" />
                        Zrušit rezervaci
                    </button>
                </div>
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

    {{-- Doctor's note: the client promised one to waive the storno fee. Uploading it
         here is what flips the badge to „Potvrzení nahráno" and alerts staff. --}}
    @if($state->isDoctorNotePending())
        @php($noteType = ReservationDocumentType::DoctorNote)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6">
            <h2 class="flex items-center gap-2 font-heading text-base font-bold text-neutral-900">
                <x-lucide name="file-text" class="h-4 w-4 shrink-0 text-amber-500" />
                Potvrzení od lékaře
            </h2>
            <p class="mt-1 text-sm leading-relaxed text-neutral-600">
                Storno poplatek {{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč je pozastavený, dokud potvrzení nezkontrolujeme.
                Nahrajte je prosím zde — stačí i fotka z telefonu.
            </p>

            @if(session('doctor_note_status'))
                <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{{ session('doctor_note_status') }}</p>
            @endif

            <label class="mt-4 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-amber-300 bg-white px-4 py-6 text-center transition hover:border-amber-400">
                <x-lucide name="upload" class="h-6 w-6 text-amber-500" />
                <span class="font-heading text-sm font-semibold text-neutral-900">Vyberte soubor</span>
                <span class="text-xs text-neutral-500">{{ $noteType->formatsLabel() }} · max {{ (int) ($noteType->maxKilobytes() / 1024) }} MB</span>
                <input
                    type="file"
                    multiple
                    wire:model="doctorNoteFiles"
                    accept="{{ $noteType->acceptAttribute() }}"
                    class="mt-2 w-full text-xs text-neutral-600 file:mr-3 file:rounded-full file:border-0 file:bg-neutral-100 file:px-4 file:py-1.5 file:font-heading file:text-xs file:font-semibold file:text-neutral-700"
                >
            </label>

            <p wire:loading wire:target="doctorNoteFiles, uploadDoctorNote" class="mt-3 text-sm text-neutral-500">Nahrávám…</p>

            @error('doctorNoteFiles.*')
                <p class="mt-3 rounded-xl bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror

            <button
                type="button"
                wire:click="uploadDoctorNote"
                wire:loading.attr="disabled"
                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
            >
                <x-lucide name="upload" class="h-4 w-4" />
                Nahrát potvrzení
            </button>

            <x-reservations.document-list :documents="$doctorNoteDocuments" remove-wire="removeDoctorNote" />

            @if($contactEmail)
                <p class="mt-4 text-xs text-neutral-500">
                    Nahrání se nedaří? Pošlete potvrzení na
                    <a href="mailto:{{ $contactEmail }}" class="font-medium text-primary-dark underline">{{ $contactEmail }}</a>.
                </p>
            @endif

            @if($canChangeStorno)
                <p class="mt-4 border-t border-amber-200 pt-4 text-xs text-neutral-500">
                    Potvrzení nakonec neseženete?
                    <button type="button" wire:click="openChangeStorno" class="font-medium text-primary-dark underline">Změnit způsob vyřešení storna</button>
                </p>
            @endif
        </div>
    @elseif($reservation->doctor_note_resolved_at && $reservation->doctorNoteDocuments->isNotEmpty())
        <div class="rounded-2xl border border-line bg-white p-6">
            <h2 class="flex items-center gap-2 font-heading text-base font-bold text-neutral-900">
                <x-lucide name="circle-check" class="h-4 w-4 shrink-0 text-emerald-500" />
                Potvrzení od lékaře
            </h2>
            <p class="mt-1 text-sm text-neutral-600">Vaše potvrzení jsme zpracovali {{ $reservation->doctor_note_resolved_at->translatedFormat('j. n. Y') }}.</p>
            <x-reservations.document-list :documents="$doctorNoteDocuments" />
        </div>
    @endif

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

            @if($canChangeStorno && ! $state->isDoctorNotePending())
                <p class="mt-5 border-t border-amber-200 pt-4 text-xs text-neutral-500">
                    Zabránily vám v návštěvě zdravotní důvody?
                    <button type="button" wire:click="openChangeStorno" class="font-medium text-primary-dark underline">Změnit způsob vyřešení storna</button>
                </p>
            @endif
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

    {{-- Confirm modal --}}
    @if($confirmingConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closeConfirm"></div>

            <div class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
                <div class="flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500">
                        <x-lucide name="circle-check" class="h-7 w-7" />
                    </span>
                </div>
                <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Potvrdit rezervaci?</h2>
                <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                    Potvrzením dáváte terapeutovi vědět, že na termín {{ $reservation->startsAt()->translatedFormat('j. n. Y · H:i') }} dorazíte.
                    Od té chvíle se na zrušení vztahují storno podmínky.
                </p>

                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" wire:click="confirmReservation" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-60">
                        <x-lucide name="check" class="h-4 w-4" />
                        Ano, potvrdit
                    </button>
                    <button type="button" wire:click="closeConfirm" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        Zpět
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Change the storno resolution. Open while the storno is unresolved — a client
         who cannot get the promised note must be able to switch to paying, and vice
         versa. Deactivation is absent from the reverse direction: it blacklists the
         account, so it is never something to change back FROM. --}}
    @if($changingStorno)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closeChangeStorno"></div>

            <div class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
                <div class="flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                        <x-lucide name="refresh-cw" class="h-7 w-7" />
                    </span>
                </div>
                <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Změnit způsob vyřešení storna</h2>
                <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                    Rezervace zůstává zrušená. Vyberte, jak chcete storno vyřešit místo dosavadní volby.
                </p>

                <div class="mt-4 flex items-center justify-between gap-4 rounded-xl bg-surface-alt px-4 py-3">
                    <span class="text-sm text-neutral-500">Storno poplatek:</span>
                    <span class="font-heading text-base font-bold text-red-600">{{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč ({{ Settings::stornoFeePercent() }} %)</span>
                </div>

                <div class="mt-5 flex flex-col gap-2.5">
                    @if($state->isDoctorNotePending())
                        <button type="button" wire:click="switchToStornoPayment" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                            <x-lucide name="credit-card" class="h-4 w-4" />
                            Potvrzení nedoložím, zaplatím storno
                        </button>
                    @else
                        <button type="button" wire:click="switchToDoctorNote" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60">
                            <x-lucide name="file-text" class="h-4 w-4" />
                            Přece jen dodám potvrzení od lékaře
                        </button>
                    @endif

                    <div x-data="{ confirmDeactivate: false }" class="contents">
                        <button type="button" x-show="!confirmDeactivate" @click="confirmDeactivate = true" class="inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] border-red-200 bg-white px-6 py-3 font-heading text-sm font-semibold text-red-600 transition hover:bg-red-50">
                            <x-lucide name="user-x" class="h-4 w-4" />
                            Nezaplatím (deaktivace účtu)
                        </button>
                        <div x-show="confirmDeactivate" x-cloak class="rounded-xl border-[1.5px] border-red-200 bg-red-50 p-4 text-left">
                            <p class="text-xs leading-relaxed text-red-700">
                                Opravdu? Váš účet bude <strong>deaktivován</strong> — nebudete se moci přihlásit ani online spravovat své rezervace a tuto volbu už online nezměníte. Pro obnovení účtu nás budete muset kontaktovat.
                            </p>
                            <p class="mt-2 text-xs leading-relaxed text-red-700">
                                Zároveň <strong>zrušíme všechny vaše budoucí rezervace, přihlášky na kurzy a lekce i místa v pořadníku</strong>{{ $deactivationPreview ? ' ('.$deactivationPreview.')' : '' }}.
                            </p>
                            <div class="mt-3 flex flex-col gap-2">
                                <button type="button" wire:click="switchToDeactivation" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-2.5 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                                    Ano, deaktivovat účet
                                </button>
                                <button type="button" @click="confirmDeactivate = false" class="rounded-full border-[1.5px] border-line bg-white px-6 py-2.5 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                                    Zpět
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" wire:click="closeChangeStorno" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        Ponechat současnou volbu
                    </button>
                </div>
            </div>
        </div>
    @endif

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
                                <p class="mt-2 text-xs leading-relaxed text-red-700">
                                    Zároveň <strong>zrušíme všechny vaše budoucí rezervace, přihlášky na kurzy a lekce i místa v pořadníku</strong>{{ $deactivationPreview ? ' ('.$deactivationPreview.')' : '' }}.
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
