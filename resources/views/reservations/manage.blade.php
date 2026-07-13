@extends('layouts.public')

@php
    use App\Enums\PaymentStatus;
    use App\Enums\ReservationStatus;

    $status = $reservation->status;
    $when = $reservation->startsAt()->translatedFormat('j. F Y, H:i');
    $service = $reservation->service?->name;
    $therapist = $reservation->therapist?->user?->name;

    $fee = $reservation->stornoFee();
    // Storno choice applies once confirmed or inside the window (and a fee applies).
    $needsStorno = $reservation->requiresStornoDecision();

    // Cancelled sub-state → which result panel to show.
    $awaitingDoctorNote = $reservation->doctor_note_requested_at !== null;
    $awaitingPayment = $reservation->payments->contains(fn ($payment): bool => $payment->status === PaymentStatus::Unpaid);
    $deactivated = (bool) $reservation->client?->isDeactivated();

    $phone = \App\Support\Settings::get('web.contact_phone');
    $submitUrl = request()->fullUrl();

    $btnBase = 'inline-flex w-full items-center justify-center rounded-full px-8 py-[15px] font-heading text-base font-semibold transition';
@endphp

@section('content')
    <section class="bg-surface-alt py-16 lg:py-24">
        <div class="ff-container">
            <div class="mx-auto max-w-lg rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm lg:p-10">

                {{-- Status heading --}}
                @if($status === ReservationStatus::Cancelled)
                    @if($awaitingDoctorNote)
                        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                            {!! \App\Support\Icon::render('file-text', 'h-7 w-7') !!}
                        </div>
                        <h1 class="font-heading text-2xl font-bold text-neutral-900">Rezervace zrušena</h1>
                        <p class="mt-2 leading-relaxed text-neutral-600">Čekáme na potvrzení od lékaře. Po jeho doručení vám storno poplatek účtovat nebudeme.</p>
                    @elseif($awaitingPayment)
                        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                            {!! \App\Support\Icon::render('banknote', 'h-7 w-7') !!}
                        </div>
                        <h1 class="font-heading text-2xl font-bold text-neutral-900">Rezervace zrušena</h1>
                        <p class="mt-2 leading-relaxed text-neutral-600">Za pozdní storno je účtován poplatek. Platební údaje včetně QR kódu jsme vám zaslali e-mailem.</p>
                    @elseif($deactivated)
                        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            {!! \App\Support\Icon::render('circle-x', 'h-7 w-7') !!}
                        </div>
                        <h1 class="font-heading text-2xl font-bold text-neutral-900">Rezervace zrušena</h1>
                        <p class="mt-2 leading-relaxed text-neutral-600">Váš účet byl deaktivován. Pro budoucí rezervace nás prosím kontaktujte{{ $phone ? ' na čísle '.$phone : '' }}.</p>
                    @else
                        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            {!! \App\Support\Icon::render('circle-x', 'h-7 w-7') !!}
                        </div>
                        <h1 class="font-heading text-2xl font-bold text-neutral-900">Rezervace byla zrušena</h1>
                        <p class="mt-2 leading-relaxed text-neutral-600">Tato rezervace byla zrušena.</p>
                    @endif
                @elseif($status === ReservationStatus::Confirmed)
                    <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-600">
                        {!! \App\Support\Icon::render('circle-check', 'h-7 w-7') !!}
                    </div>
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Rezervace potvrzena</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Děkujeme, těšíme se na vaši návštěvu.</p>
                @else
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Vaše rezervace</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Zkontrolujte prosím údaje níže. Můžete potvrdit svou účast, nebo rezervaci zrušit.</p>
                @endif

                {{-- Reservation summary --}}
                <div class="mt-6 space-y-2 rounded-xl bg-surface-alt p-5 text-left text-sm">
                    @if($service)
                        <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Služba:</span><span class="text-neutral-600">{{ $service }}</span></div>
                    @endif
                    @if($therapist)
                        <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Terapeut:</span><span class="text-neutral-600">{{ $therapist }}</span></div>
                    @endif
                    <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Datum a čas:</span><span class="text-neutral-600">{{ $when }}</span></div>
                </div>

                {{-- Actions (only while the reservation is still active) --}}
                @unless($status === ReservationStatus::Cancelled)
                    @if($status === ReservationStatus::Pending)
                        <form method="POST" action="{{ $submitUrl }}" class="mt-7">
                            @csrf
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="{{ $btnBase }} bg-primary text-white hover:bg-primary-dark">
                                Potvrdit rezervaci
                            </button>
                        </form>
                    @endif

                    @if($needsStorno)
                        <div class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-5 text-left">
                            <div class="flex items-center gap-2 font-heading text-base font-semibold text-neutral-900">
                                {!! \App\Support\Icon::render('triangle-alert', 'h-5 w-5 text-amber-600') !!}
                                <span>Zrušení rezervace</span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                                Zrušení této rezervace je zpoplatněno storno poplatkem <strong class="text-neutral-900">{{ number_format($fee, 0, ',', ' ') }} Kč</strong>. Vyberte prosím jednu z možností:
                            </p>

                            <div class="mt-4 space-y-3">
                                <form method="POST" action="{{ $submitUrl }}">
                                    @csrf
                                    <input type="hidden" name="action" value="pay">
                                    <button type="submit" class="{{ $btnBase }} bg-primary text-white hover:bg-primary-dark">
                                        Nepřijdu, zaplatím storno poplatek
                                    </button>
                                </form>
                                <form method="POST" action="{{ $submitUrl }}">
                                    @csrf
                                    <input type="hidden" name="action" value="doctor">
                                    <button type="submit" class="{{ $btnBase }} border border-neutral-300 bg-white text-neutral-800 hover:bg-neutral-50">
                                        Nepřijdu, dodám potvrzení od lékaře
                                    </button>
                                </form>
                                <form method="POST" action="{{ $submitUrl }}">
                                    @csrf
                                    <input type="hidden" name="action" value="deactivate">
                                    <button type="submit" class="{{ $btnBase }} border border-red-200 bg-white text-red-600 hover:bg-red-50">
                                        Nepřijdu a storno neuhradím
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ $submitUrl }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="{{ $btnBase }} border border-red-200 bg-white text-red-600 hover:bg-red-50">
                                Zrušit rezervaci
                            </button>
                        </form>
                    @endif
                @endunless

            </div>
        </div>
    </section>
@endsection
