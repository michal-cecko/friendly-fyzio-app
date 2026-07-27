@props([
    'reservation',
    'action',
])

@php
    use App\Enums\ReservationDocumentType;

    $type = ReservationDocumentType::DoctorNote;
    $documents = $reservation->doctorNoteDocuments()->latest()->get();
@endphp

{{-- Passwordless doctor-note upload (signed magic link). The client zone has its
     own Livewire twin of this panel; both write through ReservationDocuments. --}}
<div class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-5 text-left">
    <div class="flex items-center gap-2 font-heading text-base font-semibold text-neutral-900">
        {!! \App\Support\Icon::render('file-text', 'h-5 w-5 text-amber-600') !!}
        <span>Potvrzení od lékaře</span>
    </div>

    <p class="mt-2 text-sm leading-relaxed text-neutral-600">
        Storno poplatek <strong class="text-neutral-900">{{ number_format($reservation->stornoFee(), 0, ',', ' ') }} Kč</strong>
        je pozastaven, dokud potvrzení nezkontrolujeme. Nahrajte je prosím zde — stačí i fotka z telefonu.
    </p>

    @if(session('doctor_note_status'))
        <p class="mt-3 rounded-lg bg-green-50 px-4 py-2.5 text-sm font-medium text-green-700">{{ session('doctor_note_status') }}</p>
    @endif

    @error('documents.*')
        <p class="mt-3 rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-4">
        @csrf
        <input type="hidden" name="action" value="upload">

        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-amber-300 bg-white px-4 py-6 text-center transition hover:border-amber-400">
            <span class="text-amber-500">{!! \App\Support\Icon::render('upload', 'h-6 w-6') !!}</span>
            <span class="font-heading text-sm font-semibold text-neutral-900">Vyberte soubor</span>
            <span class="text-xs text-neutral-500">{{ $type->formatsLabel() }} · max {{ (int) ($type->maxKilobytes() / 1024) }} MB</span>
            <input type="file" name="documents[]" multiple accept="{{ $type->acceptAttribute() }}" class="mt-2 w-full text-xs text-neutral-600 file:mr-3 file:rounded-full file:border-0 file:bg-neutral-100 file:px-4 file:py-1.5 file:font-heading file:text-xs file:font-semibold file:text-neutral-700">
        </label>

        <button type="submit" class="mt-3 inline-flex w-full items-center justify-center rounded-full bg-primary px-8 py-[15px] font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
            Nahrát potvrzení
        </button>
    </form>

    <x-reservations.document-list :documents="$documents" :remove-url="$action" />
</div>
