@php
    use Illuminate\Support\Str;

    $stepLabels = [
        'therapist' => 'Terapeut',
        'category' => 'Kategorie',
        'service' => 'Služba',
        'date' => 'Datum',
        'time' => 'Čas',
        'contact' => 'Údaje',
    ];
    $order = $this->stepOrder();
    $current = $this->currentStep();
    $summary = $this->summary;

    $heading = 'font-heading text-[1.375rem] font-semibold text-neutral-900';
    $muted = 'text-[#666666]';

    // Selection is a deferred radio + CSS peer-checked — instant highlight, the value
    // only syncs to the server on the next action (Pokračovat / submit), so rapid
    // clicking never hits the network.
    $cardFlexCol = 'flex h-full flex-col items-center gap-3 rounded-xl border-2 border-line bg-white p-5 text-center transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light';
    $cardBlock = 'rounded-xl border-2 border-line bg-white p-6 text-left transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light';
    $cardRow = 'flex items-center justify-between gap-4 rounded-xl border-2 border-line bg-white p-5 text-left transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light';
    $timePill = 'inline-block rounded-xl border-2 border-line px-5 py-2.5 font-heading text-sm font-medium text-neutral-700 transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white';
@endphp

<div>
    @if ($this->confirmationId)
        {{-- ─── Success ─────────────────────────────────────────────────── --}}
        <div class="bg-surface-alt">
            <div class="ff-container py-12 text-center">
                <h1 class="font-heading text-4xl font-bold text-neutral-900">Rezervace</h1>
            </div>
        </div>
        <div class="bg-white">
            <div class="ff-container py-12">
                <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-8 text-center lg:p-12">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <h2 class="font-heading text-2xl font-bold text-neutral-900">Rezervace úspěšně odeslána!</h2>
                    <p class="mx-auto mt-3 max-w-md {{ $muted }}">Děkujeme. Termín potvrdíme telefonicky nebo e-mailem. Pokud jsme vám vytvořili účet, najdete v e-mailu pokyny k přihlášení.</p>

                    <dl class="mx-auto mt-8 max-w-sm space-y-3 rounded-xl border border-line bg-white p-5 text-left text-sm">
                        @foreach (['service' => 'Služba', 'therapist' => 'Terapeut', 'date' => 'Datum', 'time' => 'Čas'] as $key => $label)
                            @if ($summary[$key])
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="{{ $muted }}">{{ $label }}:</dt>
                                    <dd class="text-right font-semibold text-neutral-900">{{ $summary[$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('home') }}" class="rounded-full bg-primary px-7 py-3.5 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-dark">Zpět na úvod</a>
                        <button type="button" wire:click="startOver" class="rounded-full bg-primary-light px-7 py-3.5 font-heading text-[15px] font-semibold text-primary transition hover:brightness-95">Nová rezervace</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- ─── Breadcrumb ──────────────────────────────────────────────── --}}
        <div class="bg-white">
            <div class="ff-container py-4 text-sm {{ $muted }}">
                <a href="{{ route('home') }}" class="hover:text-primary">Domů</a>
                <span class="mx-2 text-neutral-300">/</span>
                <span class="text-neutral-900">Rezervace</span>
            </div>
        </div>

        {{-- ─── Page header ─────────────────────────────────────────────── --}}
        <div class="bg-surface-alt">
            <div class="ff-container py-12 text-center">
                <h1 class="font-heading text-4xl font-bold text-neutral-900">Rezervace</h1>
                <p class="mt-4 text-base {{ $muted }}">Objednejte se online. Potvrzení obdržíte emailem.</p>
            </div>
        </div>

        {{-- ─── Booking content ─────────────────────────────────────────── --}}
        <div class="bg-white">
            <div class="ff-container py-12">
                <div class="grid gap-12 lg:grid-cols-[1fr_380px]">
                    {{-- Left column: stepper + step content --}}
                    <div class="flex flex-col gap-6">
                        {{-- Stepper --}}
                        <ol class="flex items-center">
                            @foreach ($order as $index => $step)
                                @php($done = $index < $this->stepIndex)
                                @php($active = $index === $this->stepIndex)
                                @php($todo = ! $done && ! $active)
                                <li class="shrink-0">
                                    <button type="button" @disabled(! $done) wire:click="goToStep({{ $index }})" class="flex items-center gap-1.5 {{ $done ? 'cursor-pointer' : 'cursor-default' }}">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-heading text-xs {{ $todo ? 'border-2 border-line font-semibold text-[#666666]' : 'bg-primary font-bold text-white' }}">{{ $index + 1 }}</span>
                                        <span class="font-heading text-[13px] {{ $active ? 'inline' : 'hidden sm:inline' }} {{ $todo ? 'font-medium text-[#666666]' : 'font-semibold text-primary' }}">{{ $stepLabels[$step] }}</span>
                                    </button>
                                </li>
                                @unless ($loop->last)
                                    <div class="mx-2 h-0.5 flex-1 bg-line"></div>
                                @endunless
                            @endforeach
                        </ol>

                        {{-- Step content (directly on white, no card) --}}
                        <div>
                            @error('step')
                                <p class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $message }}</p>
                            @enderror
                            @if ($submitError)
                                <p class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $submitError }}</p>
                            @endif

                            {{-- Therapist --}}
                                @if ($current === 'therapist')
                                    <h2 class="{{ $heading }}">Vyberte terapeuta</h2>
                                    <p class="mt-1 text-sm {{ $muted }}">Vyberte preferovaného terapeuta.</p>
                                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                        @if ($this->therapists->count() > 1)
                                            <label wire:key="th-any" class="cursor-pointer">
                                                <input type="radio" wire:model="therapistSlug" value="any" class="peer sr-only">
                                                <div class="{{ $cardFlexCol }}">
                                                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white">
                                                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 21a8 8 0 0 0-16 0M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" /><circle cx="10" cy="8" r="5" /></svg>
                                                    </span>
                                                    <span class="font-heading text-sm font-semibold text-neutral-900">Nezáleží</span>
                                                </div>
                                            </label>
                                        @endif
                                        @foreach ($this->therapists as $therapist)
                                            @php($name = $therapist->user?->name ?? 'Terapeut')
                                            @php($firstName = \Illuminate\Support\Str::of($name)->trim()->before(' '))
                                            @php($photo = \App\Support\Media::url($therapist->photo, 'thumb'))
                                            <label wire:key="th-{{ $therapist->id }}" class="cursor-pointer">
                                                <input type="radio" wire:model="therapistSlug" value="{{ $therapist->slug }}" class="peer sr-only">
                                                <div class="{{ $cardFlexCol }}">
                                                    <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-primary font-heading text-base font-semibold text-white">
                                                        @if ($photo)
                                                            <img src="{{ $photo }}" alt="{{ $name }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ \App\Support\Avatar::initials($name) }}
                                                        @endif
                                                    </span>
                                                    <span class="font-heading text-sm font-semibold text-neutral-900">{{ $firstName }}</span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                {{-- Category --}}
                                @elseif ($current === 'category')
                                    <h2 class="{{ $heading }}">Vyberte kategorii</h2>
                                    <p class="mt-1 text-sm {{ $muted }}">Zvolte oblast služeb, která vás zajímá.</p>
                                    <div class="mt-5 grid gap-3 lg:grid-cols-3">
                                        @forelse ($this->categories as $category)
                                            <label wire:key="cat-{{ $category->slug }}" class="cursor-pointer">
                                                <input type="radio" wire:model="categorySlug" value="{{ $category->slug }}" class="peer sr-only">
                                                <div class="flex h-full flex-col items-center gap-3 rounded-xl border-2 border-line bg-white px-6 py-8 text-center text-[#AAAAAA] transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light peer-checked:text-primary">
                                                    @if ($category->icon)
                                                        {!! \App\Support\Icon::render($category->icon, 'h-10 w-10') !!}
                                                    @endif
                                                    <span class="font-heading text-lg font-bold text-neutral-900">{{ $category->name }}</span>
                                                    @if ($category->description)
                                                        <span class="text-[13px] text-[#666666]">{{ Str::limit(strip_tags($category->description), 80) }}</span>
                                                    @endif
                                                </div>
                                            </label>
                                        @empty
                                            <p class="{{ $muted }}">Žádné kategorie k dispozici.</p>
                                        @endforelse
                                    </div>

                                {{-- Service --}}
                                @elseif ($current === 'service')
                                    @if ($this->isPhysioCategory())
                                        <h2 class="{{ $heading }}">{{ $this->category?->name }} — vyberte vyšetření a službu</h2>
                                        <p class="mt-1 text-sm {{ $muted }}">Nejprve zvolte typ vyšetření, poté konkrétní službu.</p>

                                        {{-- 1. Typ vyšetření --}}
                                        <div class="mt-5">
                                            <div class="mb-3 flex items-center gap-2">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">1</span>
                                                <span class="font-heading text-[15px] font-semibold text-neutral-900">Typ vyšetření</span>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                @foreach ($this->examTypes as $type)
                                                    @php($selected = $this->isExamTypeSelected($type))
                                                    <button type="button" wire:key="exam-{{ $type->value }}" wire:click="selectExamType('{{ $type->value }}')" wire:loading.attr="disabled" class="flex items-center justify-between gap-3 rounded-xl border-2 p-4 text-left font-heading text-[15px] font-semibold text-neutral-900 transition {{ $selected ? 'border-primary bg-primary-light' : 'border-line bg-white hover:border-primary' }}">
                                                        <span>{{ $type->getLabel() }}</span>
                                                        @if ($type === \App\Enums\ExamType::Kontrolni)
                                                            <svg class="h-4 w-4 shrink-0 {{ $selected ? 'text-primary' : 'text-[#AAAAAA]' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Gate / lapsed notice / specific-service grid --}}
                                        @if ($gate === 'login')
                                            <div class="mt-4">@include('livewire.reservation.login-callout')</div>
                                        @elseif ($gate === 'lapsed')
                                            <div class="mt-4 rounded-xl border-2 border-amber-300 bg-amber-50 p-5">
                                                <p class="text-sm text-amber-900">Vaše poslední návštěva proběhla před více než <strong>{{ $lapsedMonths }} měsíci</strong>, proto si můžete zvolit pouze vstupní vyšetření.</p>
                                                <button type="button" wire:click="selectExamType('{{ \App\Enums\ExamType::Vstupni->value }}')" class="mt-3 rounded-full bg-primary px-7 py-3 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-dark">Pokračovat jako nový pacient</button>
                                            </div>
                                        @elseif (filled($this->examType))
                                            <div class="mt-6">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">2</span>
                                                    <span class="font-heading text-[15px] font-semibold text-neutral-900">Konkrétní služba</span>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    @forelse ($this->services as $service)
                                                        @include('livewire.reservation.service-card', ['service' => $service, 'category' => $this->category])
                                                    @empty
                                                        <p class="{{ $muted }}">Pro tento typ nejsou dostupné žádné služby.</p>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <h2 class="{{ $heading }}">{{ $this->category?->name }} — vyberte službu</h2>
                                        <p class="mt-1 text-sm {{ $muted }}">Zvolte konkrétní službu a její délku.</p>
                                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                            @forelse ($this->services as $service)
                                                @include('livewire.reservation.service-card', ['service' => $service, 'category' => $this->category])
                                            @empty
                                                <p class="{{ $muted }}">V této kategorii nejsou dostupné žádné služby.</p>
                                            @endforelse
                                        </div>
                                    @endif

                                {{-- Date — month switching is client-side (Alpine); only the day picked
                                     syncs (deferred) so the slot engine never reruns on navigation. --}}
                                @elseif ($current === 'date')
                                    <h2 class="{{ $heading }}">Vyberte datum</h2>
                                    <p class="mt-1 text-sm {{ $muted }}">Vyberte preferovaný termín.</p>
                                    @php($months = $this->calendarMonths())
                                    <div class="mt-5" x-data="{ m: {{ $this->initialCalendarIndex() }}, total: {{ count($months) }}, labels: @js(array_column($months, 'label')) }">
                                        {{-- Calendar card --}}
                                        <div class="rounded-xl border border-line p-6">
                                            {{-- Month navigation --}}
                                            <div class="flex items-center justify-between">
                                                <button type="button" @click="m = Math.max(0, m - 1)" :disabled="m === 0" class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-600 transition hover:bg-surface-alt disabled:opacity-30" aria-label="Předchozí měsíc">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" /></svg>
                                                </button>
                                                <span class="font-heading text-base font-semibold text-neutral-900" x-text="labels[m]"></span>
                                                <button type="button" @click="m = Math.min(total - 1, m + 1)" :disabled="m === total - 1" class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-600 transition hover:bg-surface-alt disabled:opacity-30" aria-label="Další měsíc">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" /></svg>
                                                </button>
                                            </div>

                                            {{-- Weekday headers --}}
                                            <div class="mt-4 grid grid-cols-7 gap-1">
                                                @foreach (['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $weekday)
                                                    <span class="flex h-10 items-center justify-center text-xs font-semibold text-[#666666]">{{ $weekday }}</span>
                                                @endforeach
                                            </div>

                                            {{-- Day grid — one grid per month, toggled client-side --}}
                                            @foreach ($months as $i => $month)
                                                <div @if ($i !== $this->initialCalendarIndex()) x-cloak @endif x-show="m === {{ $i }}" class="grid grid-cols-7 gap-y-1">
                                                    @foreach ($month['weeks'] as $week)
                                                        @foreach ($week as $cell)
                                                            @if ($cell === null)
                                                                <span class="h-10"></span>

                                                            {{-- Full day → join the pořadník (waitlist) for this therapist's day. --}}
                                                            @elseif ($cell['queue'] === 'full')
                                                                <button type="button" wire:click="openWaitlist('{{ $cell['date'] }}')" class="mx-auto flex h-10 w-10 flex-col items-center justify-center gap-0.5 rounded-full bg-amber-100 text-sm font-semibold text-amber-500 transition hover:bg-amber-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400" title="Zapsat se do pořadníku">
                                                                    {{ $cell['day'] }}
                                                                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                                                                </button>

                                                            {{-- Already on this day's pořadník. --}}
                                                            @elseif ($cell['queue'] === 'waitlist')
                                                                <span class="mx-auto flex h-10 w-10 flex-col items-center justify-center gap-0.5 rounded-full bg-surface-alt text-sm font-medium text-[#666666]" title="Jste v pořadníku">
                                                                    {{ $cell['day'] }}
                                                                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 22h14M5 2h14M17 22v-4.17a2 2 0 0 0-.59-1.42L12 12l-4.41 4.41A2 2 0 0 0 7 17.83V22M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2" /></svg>
                                                                </span>

                                                            {{-- Available → selectable. `today` gets the ring; selected (peer-checked) fills. --}}
                                                            @elseif ($cell['available'])
                                                                <label wire:key="day-{{ $cell['date'] }}" class="flex cursor-pointer justify-center">
                                                                    <input type="radio" wire:model="date" value="{{ $cell['date'] }}" class="peer sr-only">
                                                                    <span @class([
                                                                        'relative flex h-10 w-10 items-center justify-center rounded-full text-sm transition',
                                                                        'font-normal text-neutral-900' => ! $cell['today'],
                                                                        'border-2 border-primary font-semibold text-primary' => $cell['today'],
                                                                        'peer-checked:border-transparent peer-checked:bg-primary peer-checked:font-semibold peer-checked:text-white',
                                                                    ])>
                                                                        {{ $cell['day'] }}
                                                                        <span class="absolute bottom-1 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-primary"></span>
                                                                    </span>
                                                                </label>

                                                            {{-- Today, but no free slots — marked but not selectable. --}}
                                                            @elseif ($cell['today'])
                                                                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary text-sm font-semibold text-primary">{{ $cell['day'] }}</span>

                                                            {{-- Unavailable / past. --}}
                                                            @else
                                                                <span class="mx-auto flex h-10 w-10 items-center justify-center text-sm text-[#888888]">{{ $cell['day'] }}</span>
                                                            @endif
                                                        @endforeach
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- Legend --}}
                                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-[#666666]">
                                            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-primary"></span>Vybraný den</span>
                                            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-primary"></span>Dnes</span>
                                            <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-primary"></span>Dostupné</span>
                                            <span class="flex items-center gap-1.5"><span class="text-[11px] text-[#888888]">5</span>Nedostupné</span>
                                            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border border-amber-500 bg-amber-100"></span>Plno — zapsat do pořadníku</span>
                                            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border border-line bg-surface-alt"></span>Jste v pořadníku</span>
                                        </div>

                                        @if ($this->availableDays === [])
                                            <p class="mt-4 rounded-xl bg-surface-muted px-4 py-3 text-center text-sm {{ $muted }}">V nejbližší době nejsou volné termíny. Kontaktujte nás na +420 604 793 255.</p>
                                        @endif
                                    </div>

                                {{-- Time --}}
                                @elseif ($current === 'time')
                                    <h2 class="{{ $heading }}">Vyberte čas</h2>
                                    <p class="mt-1 text-sm {{ $muted }}">{{ $summary['date'] }}</p>
                                    @php($times = collect($this->availableTimes)->map->start()->unique()->values())
                                    @if ($times->isEmpty())
                                        <p class="mt-5 rounded-xl bg-surface-muted px-4 py-3 text-sm {{ $muted }}">Pro tento den už nejsou volné časy. Zvolte prosím jiné datum.</p>
                                    @else
                                        <div class="mt-5 flex flex-wrap gap-2.5">
                                            @foreach ($times as $time)
                                                <label wire:key="time-{{ $time }}" class="cursor-pointer">
                                                    <input type="radio" wire:model="startTime" value="{{ $time }}" class="peer sr-only">
                                                    <span class="{{ $timePill }}">{{ $time }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    <p class="mt-6 text-sm {{ $muted }}">Nevyhovuje Vám žádný termín? Kontaktujte nás na <span class="font-semibold text-neutral-700">+420 604 793 255</span>.</p>

                                {{-- Contact --}}
                                @elseif ($current === 'contact')
                                    @if ($gate === 'email_exists')
                                    @include('livewire.reservation.login-callout')
                                    @else
                                    <h2 class="{{ $heading }}">Vyplňte své údaje</h2>
                                    <p class="mt-1 text-sm {{ $muted }}">Termín vám potvrdíme telefonicky nebo e-mailem.</p>
                                    @php($inputClass = 'w-full rounded-xl border border-line px-4 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30')
                                    <div class="mt-5 grid gap-4">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-neutral-700">Jméno</label>
                                                <input type="text" wire:model="firstName" class="{{ $inputClass }}">
                                                @error('firstName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-neutral-700">Příjmení</label>
                                                <input type="text" wire:model="lastName" class="{{ $inputClass }}">
                                                @error('lastName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-neutral-700">Stručný popis problému / týden těhotenství <span class="text-neutral-400">(nepovinné)</span></label>
                                            <textarea wire:model="note" rows="3" class="{{ $inputClass }}" placeholder="únik moči, diastáza, bolesti…"></textarea>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-neutral-700">Telefon</label>
                                            <input type="tel" wire:model="phone" class="{{ $inputClass }}" placeholder="+420">
                                            @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-neutral-700">E-mail</label>
                                            <input type="email" wire:model.blur="email" class="{{ $inputClass }}" placeholder="@">
                                            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                            @if ($emailKnown && auth()->guest())
                                                <p class="mt-1.5 text-sm {{ $muted }}">Tento e-mail už známe — rezervaci automaticky přiřadíme k vašemu účtu. <button type="button" wire:click="showLogin" class="text-primary-dark underline">Chcete se přihlásit?</button></p>
                                            @endif
                                        </div>
                                        <label class="flex items-start gap-3 text-sm text-neutral-600">
                                            <input type="checkbox" wire:model="agreeCancellation" class="mt-0.5 h-4 w-4 rounded border-line text-primary focus:ring-primary/30">
                                            <span>Souhlasím se <a href="/storno-podminky" class="text-primary-dark underline">storno podmínkami</a>.</span>
                                        </label>
                                        @error('agreeCancellation') <span class="-mt-2 block text-xs text-red-600">{{ $message }}</span> @enderror
                                        <label class="flex items-start gap-3 text-sm text-neutral-600">
                                            <input type="checkbox" wire:model="newsletter" class="mt-0.5 h-4 w-4 rounded border-line text-primary focus:ring-primary/30">
                                            <span>Chci dostávat informace o kurzech, workshopech a novinkách e-mailem.</span>
                                        </label>
                                    </div>
                                    @endif
                                @endif

                                {{-- Shared navigation --}}
                                <div class="mt-8 flex items-center justify-between gap-3 pt-4">
                                    @if ($this->stepIndex > 0)
                                        <button type="button" wire:click="back" class="inline-flex items-center gap-2 rounded-full bg-primary-light px-7 py-3.5 font-heading text-[15px] font-semibold text-primary transition hover:brightness-95">
                                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M12 19l-7-7 7-7" /></svg>
                                            Zpět
                                        </button>
                                    @else
                                        <span></span>
                                    @endif
                                    <button type="button" wire:click="next" wire:loading.attr="disabled" wire:loading.class="cursor-wait opacity-70" class="inline-flex items-center gap-2 rounded-full bg-primary px-7 py-3.5 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                                        {{ $current === 'contact' ? 'Potvrdit rezervaci' : 'Pokračovat' }}
                                        @unless ($current === 'contact')
                                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" /></svg>
                                        @endunless
                                    </button>
                                </div>
                        </div>
                    </div>

                    {{-- Right column: summary --}}
                    <aside class="h-fit rounded-2xl bg-surface-alt p-8 lg:sticky lg:top-6">
                        <h3 class="font-heading text-xl font-semibold text-neutral-900">Souhrn rezervace</h3>
                        <div class="my-6 h-px bg-line"></div>
                        <dl class="space-y-3 rounded-xl border border-line bg-white p-5 text-sm">
                            @foreach (['category' => 'Kategorie', 'service' => 'Služba', 'therapist' => 'Terapeut', 'date' => 'Datum', 'time' => 'Čas'] as $key => $label)
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="{{ $muted }}">{{ $label }}:</dt>
                                    <dd class="text-right font-semibold text-neutral-900">{{ $summary[$key] ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="my-6 h-px bg-line"></div>
                        <button type="button" wire:click="submit" @disabled(! $this->canConfirm()) wire:loading.attr="disabled" wire:loading.class="cursor-wait opacity-70" class="flex w-full items-center justify-center gap-2.5 rounded-full px-9 py-[18px] font-heading text-base font-semibold transition {{ $this->canConfirm() ? 'bg-primary text-white hover:bg-primary-dark' : 'cursor-not-allowed bg-primary/40 text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            Potvrdit rezervaci
                        </button>
                        <p class="mt-4 text-center text-xs text-[#888888]">Po potvrzení obdržíte email s detaily rezervace.</p>
                    </aside>
                </div>
            </div>
        </div>
    @endif

    {{-- Day-waitlist ("pořadník") modal --}}
    @if ($waitlistModalDate)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/40" wire:click="closeWaitlist"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-line bg-white p-7 shadow-xl">
                <h3 class="font-heading text-xl font-semibold text-neutral-900">Zapsat se do pořadníku</h3>
                <p class="mt-2 text-sm {{ $muted }}">
                    Den <strong>{{ \Illuminate\Support\Carbon::parse($waitlistModalDate)->locale('cs')->isoFormat('D. MMMM YYYY') }}</strong>
                    je plně obsazený. Necháme vám vědět e-mailem, jakmile se u vybraného terapeuta na tento den uvolní místo.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="waitlistName" class="mb-1 block text-sm font-medium text-neutral-900">Jméno a příjmení</label>
                        <input id="waitlistName" type="text" wire:model="waitlistName" class="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        @error('waitlistName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlistEmail" class="mb-1 block text-sm font-medium text-neutral-900">E-mail</label>
                        <input id="waitlistEmail" type="email" wire:model="waitlistEmail" class="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        @error('waitlistEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlistPhone" class="mb-1 block text-sm font-medium text-neutral-900">Telefon <span class="{{ $muted }}">(nepovinné)</span></label>
                        <input id="waitlistPhone" type="tel" wire:model="waitlistPhone" class="w-full rounded-xl border border-line px-4 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        @error('waitlistPhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" wire:click="closeWaitlist" class="rounded-full px-5 py-2.5 text-sm font-semibold text-[#666666] transition hover:bg-surface-alt">Zrušit</button>
                    <button type="button" wire:click="joinDayWaitlist" wire:loading.attr="disabled" class="rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">Zapsat do pořadníku</button>
                </div>
            </div>
        </div>
    @endif

    @if (session('waitlist_joined'))
        <div class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-full bg-neutral-900 px-6 py-3 text-sm font-medium text-white shadow-lg" x-data x-init="setTimeout(() => $el.remove(), 6000)">
            {{ session('waitlist_joined') }}
        </div>
    @endif
</div>
