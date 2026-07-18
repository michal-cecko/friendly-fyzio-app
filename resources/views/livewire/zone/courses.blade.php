@php
    use App\Enums\PaymentStatus;
    use App\Support\Media;

    $cardClass = 'flex flex-col overflow-hidden rounded-2xl border border-line bg-white';
    $badge = fn (string $classes, string $label) => '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes.'">'.$label.'</span>';
@endphp

<div class="flex flex-col gap-5">
    <h1 class="font-heading text-2xl font-bold text-neutral-900">Moje kurzy</h1>

    @if(session('status'))
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif

    @error('cancel') <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror
    @error('excuse') <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-line">
        @foreach(['aktualni' => 'Aktuální', 'minule' => 'Minulé'] as $key => $label)
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

    @if($enrollments->isEmpty() && $registrations->isEmpty() && $bookings->isEmpty())
        <div class="rounded-2xl border border-line bg-white px-6 py-12 text-center">
            <p class="font-heading text-base font-semibold text-neutral-900">
                {{ $tab === 'aktualni' ? 'Zatím nejste přihlášeni na žádný kurz.' : 'Zatím tu nejsou žádné minulé přihlášky.' }}
            </p>
            <p class="mt-2 text-sm text-neutral-500">
                Prohlédněte si <a href="{{ url('/kurzy') }}" class="font-medium text-primary-dark underline">nabídku kurzů</a>
                nebo <a href="{{ url('/workshopy') }}" class="font-medium text-primary-dark underline">workshopů</a>.
            </p>
        </div>
    @endif

    {{-- Course runs --}}
    @if($enrollments->isNotEmpty())
        <div class="flex flex-col gap-4">
            <h2 class="font-heading text-base font-bold text-neutral-900">Pohybové kurzy</h2>

            @foreach($enrollments as $enrollment)
                @php($series = $enrollment->series)
                <div class="{{ $cardClass }}">
                    <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $series?->course?->name ?? 'Kurz' }}</h3>
                                @if($enrollment->payment_status === PaymentStatus::Paid)
                                    {!! $badge('bg-emerald-50 text-emerald-700', 'Zaplaceno') !!}
                                @elseif($enrollment->status->value === 'cancelled')
                                    {!! $badge('bg-neutral-100 text-neutral-600', 'Zrušeno') !!}
                                @else
                                    {!! $badge('bg-amber-50 text-amber-700', 'Čeká na platbu') !!}
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-neutral-500">
                                {{ $series?->name }}
                                @if($series?->start_date) · {{ $series->start_date->format('j. n. Y') }} – {{ $series->end_date?->format('j. n. Y') }} @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            @if($series)
                                <button
                                    type="button"
                                    wire:click="toggleLessons('{{ $enrollment->id }}')"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-1.5 font-heading text-xs font-semibold text-neutral-700 transition hover:border-primary hover:text-primary"
                                >
                                    <x-lucide name="calendar-days" class="h-3.5 w-3.5" />
                                    {{ $expandedEnrollmentId === $enrollment->id ? 'Skrýt lekce' : 'Termíny lekcí' }}
                                </button>
                            @endif

                            @if($canCancel($enrollment))
                                <button
                                    type="button"
                                    wire:click="confirmCancel('enrollment', '{{ $enrollment->id }}')"
                                    class="rounded-full border-[1.5px] border-red-200 bg-white px-4 py-1.5 font-heading text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                >Odhlásit se</button>
                            @endif
                        </div>
                    </div>

                    {{-- Lessons + excuse --}}
                    @if($expandedEnrollmentId === $enrollment->id)
                        <div class="border-t border-line bg-surface-alt px-5 py-4">
                            @if($lessonRows === [])
                                <p class="text-sm text-neutral-500">Tato série už nemá naplánované další lekce.</p>
                            @else
                                <div class="flex flex-col gap-2">
                                    @foreach($lessonRows as $row)
                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-2.5">
                                            <div class="text-sm">
                                                <span class="font-medium text-neutral-900">{{ $row['lesson']->lesson_date->translatedFormat('j. n. Y') }}</span>
                                                <span class="text-neutral-500"> · {{ \Illuminate\Support\Str::substr($row['lesson']->start_time, 0, 5) }}</span>
                                                @if($row['lesson']->room?->name)
                                                    <span class="text-neutral-500"> · {{ $row['lesson']->room->name }}</span>
                                                @endif
                                            </div>

                                            @if($row['excused'])
                                                <span class="text-xs font-semibold {{ $row['token'] ? 'text-emerald-600' : 'text-neutral-500' }}">
                                                    {{ $row['token'] ? 'Omluveno · náhradní vstup vydán' : 'Omluveno · bez náhrady' }}
                                                </span>
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="excuseFromLesson('{{ $enrollment->id }}', '{{ $row['lesson']->id }}')"
                                                    wire:loading.attr="disabled"
                                                    class="rounded-full border border-line bg-white px-3.5 py-1 text-xs font-semibold text-neutral-600 transition hover:border-primary hover:text-primary disabled:opacity-60"
                                                >Omluvit se</button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs text-neutral-500">
                                    Při včasné omluvě vám vystavíme náhradní vstup do souběžné skupiny — uplatníte ho v
                                    <a href="{{ route('zone.tokens') }}" class="font-medium text-primary-dark underline">Náhradních vstupech</a>.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Workshops --}}
    @if($registrations->isNotEmpty())
        <div class="flex flex-col gap-4">
            <h2 class="font-heading text-base font-bold text-neutral-900">Workshopy</h2>
            @foreach($registrations as $registration)
                <div class="{{ $cardClass }} flex-row flex-wrap items-center justify-between gap-4 p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $registration->workshop?->name ?? 'Workshop' }}</h3>
                            @if($registration->payment_status === PaymentStatus::Paid)
                                {!! $badge('bg-emerald-50 text-emerald-700', 'Zaplaceno') !!}
                            @elseif($registration->status === \App\Enums\BookingStatus::Cancelled)
                                {!! $badge('bg-neutral-100 text-neutral-600', 'Zrušeno') !!}
                            @else
                                {!! $badge('bg-amber-50 text-amber-700', 'Čeká na platbu') !!}
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-neutral-500">{{ $registration->workshop?->startsAt()?->translatedFormat('j. n. Y · H:i') }}</p>
                    </div>

                    @if($canCancel($registration))
                        <button
                            type="button"
                            wire:click="confirmCancel('registration', '{{ $registration->id }}')"
                            class="shrink-0 rounded-full border-[1.5px] border-red-200 bg-white px-4 py-1.5 font-heading text-xs font-semibold text-red-600 transition hover:bg-red-50"
                        >Odhlásit se</button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- One-time lessons --}}
    @if($bookings->isNotEmpty())
        <div class="flex flex-col gap-4">
            <h2 class="font-heading text-base font-bold text-neutral-900">Jednorázové lekce</h2>
            @foreach($bookings as $booking)
                <div class="{{ $cardClass }} flex-row flex-wrap items-center justify-between gap-4 p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $booking->lesson?->course?->name ?? 'Lekce' }}</h3>
                            @if($booking->payment_status === PaymentStatus::Paid)
                                {!! $badge('bg-emerald-50 text-emerald-700', 'Zaplaceno') !!}
                            @elseif($booking->status === \App\Enums\BookingStatus::Cancelled)
                                {!! $badge('bg-neutral-100 text-neutral-600', 'Zrušeno') !!}
                            @else
                                {!! $badge('bg-amber-50 text-amber-700', 'Čeká na platbu') !!}
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-neutral-500">
                            {{ $booking->lesson?->startsAt()?->translatedFormat('j. n. Y · H:i') }}
                            @if($booking->lesson?->room?->name) · {{ $booking->lesson->room->name }} @endif
                        </p>
                    </div>

                    @if($canCancel($booking))
                        <button
                            type="button"
                            wire:click="confirmCancel('booking', '{{ $booking->id }}')"
                            class="shrink-0 rounded-full border-[1.5px] border-red-200 bg-white px-4 py-1.5 font-heading text-xs font-semibold text-red-600 transition hover:bg-red-50"
                        >Odhlásit se</button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Cancel confirmation --}}
    @if($cancelling)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="closeCancel"></div>

            <div class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl">
                <div class="flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                        <x-lucide name="triangle-alert" class="h-7 w-7" />
                    </span>
                </div>

                <h2 class="mt-5 text-center font-heading text-xl font-bold text-neutral-900">Opravdu se odhlásit?</h2>
                <p class="mt-2 text-center text-sm leading-relaxed text-neutral-500">
                    Vaše místo uvolníme dalším zájemcům.
                    @if($cancelling->payment_status === PaymentStatus::Paid)
                        Přihlášku máte zaplacenou — ozveme se vám ohledně vrácení platby nebo převodu na kredit.
                    @endif
                </p>

                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" wire:click="cancelSignup" wire:loading.attr="disabled" class="rounded-full bg-red-500 px-6 py-3 font-heading text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                        Ano, odhlásit se
                    </button>
                    <button type="button" wire:click="closeCancel" class="rounded-full border-[1.5px] border-line bg-white px-6 py-3 font-heading text-sm font-semibold text-neutral-900 transition hover:bg-surface-alt">
                        Ponechat přihlášku
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
