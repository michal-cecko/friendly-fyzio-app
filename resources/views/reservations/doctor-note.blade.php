@extends('layouts.public')

@php
    $when = $reservation->startsAt()->translatedFormat('j. F Y, H:i');
    $service = $reservation->service?->name;
    $therapist = $reservation->therapist?->user?->full_name;
    $deactivated = (bool) $reservation->client?->isDeactivated();
    $awaiting = $reservation->awaitsDoctorNote();
    // The client swapped the note for paying the fee — still open, just differently.
    $awaitingPayment = ! $awaiting && ! $deactivated && $reservation->hasUnpaidStornoFee();
    $phone = \App\Support\Settings::get('web.contact_phone');
    // fullUrl keeps the signature params on the POST target.
    $submitUrl = request()->fullUrl();
    $deactivationPreview = $reservation->client
        ? app(\App\Support\Clients\DeactivateAccount::class)->previewSentence($reservation->client)
        : null;
@endphp

@section('content')
    <section class="bg-surface-alt py-16 lg:py-24">
        <div class="ff-container">
            <div class="mx-auto max-w-lg rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm lg:p-10">

                @if($deactivated)
                    <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                        {!! \App\Support\Icon::render('circle-x', 'h-7 w-7') !!}
                    </div>
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Účet byl deaktivován</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Rozhodli jste se storno poplatek neuhradit. Pro obnovení účtu nás prosím kontaktujte{{ $phone ? ' na čísle '.$phone : '' }}.</p>
                @elseif($awaiting)
                    <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                        {!! \App\Support\Icon::render('file-text', 'h-7 w-7') !!}
                    </div>
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Doručení potvrzení od lékaře</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Nahrajte prosím potvrzení k níže uvedené zrušené rezervaci.</p>
                @elseif($awaitingPayment)
                    <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                        {!! \App\Support\Icon::render('banknote', 'h-7 w-7') !!}
                    </div>
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Storno poplatek k úhradě</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Platební údaje včetně QR kódu jsme vám zaslali e-mailem.</p>
                @else
                    <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-600">
                        {!! \App\Support\Icon::render('circle-check', 'h-7 w-7') !!}
                    </div>
                    <h1 class="font-heading text-2xl font-bold text-neutral-900">Storno je vyřešeno</h1>
                    <p class="mt-2 leading-relaxed text-neutral-600">Vaše potvrzení jsme zpracovali, další soubory už nahrávat nemusíte.</p>
                @endif

                @if(session('doctor_note_status') && ! $awaiting)
                    <p class="mt-5 rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('doctor_note_status') }}</p>
                @endif

                {{-- Reservation summary --}}
                <div class="mt-6 space-y-2 rounded-xl bg-surface-alt p-5 text-left text-sm">
                    @if($service)
                        <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Služba:</span><span class="text-neutral-600">{{ $service }}</span></div>
                    @endif
                    @if($therapist)
                        <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Terapeut:</span><span class="text-neutral-600">{{ $therapist }}</span></div>
                    @endif
                    <div class="flex gap-3"><span class="w-28 shrink-0 font-semibold text-neutral-900">Původní termín:</span><span class="text-neutral-600">{{ $when }}</span></div>
                </div>

                @if($awaiting)
                    <x-reservations.doctor-note-upload :reservation="$reservation" :action="$submitUrl" />
                @else
                    <x-reservations.document-list :documents="$reservation->doctorNoteDocuments()->latest()->get()" />
                @endif

                {{-- Changed their mind: the note cannot be obtained after all. --}}
                @if($reservation->canChangeStornoResolution())
                    <div class="mt-7 rounded-xl border border-neutral-200 p-5 text-left">
                        <p class="font-heading text-sm font-semibold text-neutral-900">Potvrzení nakonec neseženete?</p>
                        <p class="mt-1 text-sm leading-relaxed text-neutral-600">
                            Storno poplatek činí <strong class="text-neutral-900">{{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč</strong>. Můžete jej místo potvrzení uhradit.
                        </p>

                        <form method="POST" action="{{ $submitUrl }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="action" value="pay">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-primary px-8 py-[15px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                                Zaplatím storno poplatek
                            </button>
                        </form>

                        <div x-data="{ confirmDeactivate: false }" class="mt-3">
                            <button type="button" x-show="!confirmDeactivate" @click="confirmDeactivate = true" class="inline-flex w-full items-center justify-center rounded-full border border-red-200 bg-white px-8 py-[15px] font-heading text-base font-semibold text-red-600 transition hover:bg-red-50">
                                Nezaplatím (deaktivace účtu)
                            </button>
                            <div x-show="confirmDeactivate" x-cloak class="rounded-xl border border-red-200 bg-red-50 p-4">
                                <p class="text-sm leading-relaxed text-red-700">
                                    Opravdu? Váš účet bude <strong>deaktivován</strong> — nebudete se moci přihlásit ani online spravovat rezervace a tuto volbu už online nezměníte.
                                </p>
                                <p class="mt-2 text-sm leading-relaxed text-red-700">
                                    Zároveň <strong>zrušíme všechny vaše budoucí rezervace, přihlášky na kurzy a lekce i místa v pořadníku</strong>{{ $deactivationPreview ? ' ('.$deactivationPreview.')' : '' }}.
                                </p>
                                <form method="POST" action="{{ $submitUrl }}" class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="action" value="deactivate">
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-red-500 px-8 py-[15px] font-heading text-base font-semibold text-white transition hover:bg-red-600">
                                        Ano, deaktivovat účet
                                    </button>
                                    <button type="button" @click="confirmDeactivate = false" class="inline-flex w-full items-center justify-center rounded-full border border-neutral-300 bg-white px-8 py-[15px] font-heading text-base font-semibold text-neutral-800 transition hover:bg-neutral-50">
                                        Zpět
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </section>
@endsection
