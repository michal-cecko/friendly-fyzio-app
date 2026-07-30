@php
    use App\Support\Media;
@endphp

<div class="flex flex-col gap-8">
    @if($showTypeSwitch)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <button
                type="button"
                wire:click="selectType('kurzy')"
                @class([
                    'flex items-center gap-5 rounded-2xl p-6 text-left transition',
                    'border-2 border-primary bg-primary-light' => ! $isEvents,
                    'border border-line bg-surface-alt hover:border-primary/50' => $isEvents,
                ])
            >
                <span @class([
                    'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl',
                    'bg-primary text-white' => ! $isEvents,
                    'bg-primary-light text-primary' => $isEvents,
                ])>
                    <x-lucide name="activity" class="h-6 w-6" />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="font-heading text-base font-semibold text-neutral-900">{{ $coursesLabel }}</span>
                    @if($coursesSubtitle !== '')
                        <span class="text-sm text-neutral-600">{{ $coursesSubtitle }}</span>
                    @endif
                </span>
                <x-lucide name="chevron-right" class="h-5 w-5 shrink-0 text-primary" />
            </button>

            <button
                type="button"
                wire:click="selectType('lekce')"
                @class([
                    'flex items-center gap-5 rounded-2xl p-6 text-left transition',
                    'border-2 border-primary bg-primary-light' => $isEvents,
                    'border border-line bg-surface-alt hover:border-primary/50' => ! $isEvents,
                ])
            >
                <span @class([
                    'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl',
                    'bg-primary text-white' => $isEvents,
                    'bg-primary-light text-primary' => ! $isEvents,
                ])>
                    <x-lucide name="clock-3" class="h-6 w-6" />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="font-heading text-base font-semibold text-neutral-900">{{ $eventsLabel }}</span>
                    @if($eventsSubtitle !== '')
                        <span class="text-sm text-neutral-600">{{ $eventsSubtitle }}</span>
                    @endif
                </span>
                <x-lucide name="chevron-right" class="h-5 w-5 shrink-0 text-primary" />
            </button>
        </div>
    @endif

    @if($showFilters || $showSearch)
        <div class="flex flex-col gap-4">
            @if($showSearch)
                <label class="relative block max-w-md">
                    <x-lucide name="search" class="pointer-events-none absolute left-4 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-neutral-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="{{ $isEvents ? 'Hledat lekci…' : 'Hledat kurz…' }}"
                        class="w-full rounded-full border border-line bg-white py-3 pl-11 pr-5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </label>
            @endif

            @if($showFilters)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                        <input type="checkbox" wire:model.live="availableOnly" class="h-4.5 w-4.5 rounded border-line text-primary focus:ring-primary/30">
                        Jenom dostupné
                    </label>

                    {{-- Pills switch taxonomy with the tab: course categories on the
                         kurzy tab, event categories on the lekce tab. A tab pinned to
                         a single event category renders no pills at all. --}}
                    @if($categories->isNotEmpty())
                        <div class="flex flex-wrap justify-center gap-2">
                            <button
                                type="button"
                                wire:click="selectCategory(null)"
                                @class([
                                    'rounded-full px-4.5 py-2 font-heading text-[13px] font-semibold transition',
                                    'bg-primary text-white' => $category === null,
                                    'border border-line bg-white text-neutral-700 hover:border-primary hover:text-primary' => $category !== null,
                                ])
                            >Vše</button>
                            @foreach($categories as $pillCategory)
                                <button
                                    type="button"
                                    wire:click="selectCategory(@js($pillCategory->slug))"
                                    @class([
                                        'rounded-full px-4.5 py-2 font-heading text-[13px] font-medium transition',
                                        'bg-primary text-white' => $category === $pillCategory->slug,
                                        'border border-line bg-white text-neutral-700 hover:border-primary hover:text-primary' => $category !== $pillCategory->slug,
                                    ])
                                >{{ $pillCategory->name }}</button>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-[13px] text-neutral-500">
                        @if($isEvents)
                            {{ 'Zobrazeno '.$results->total().' '.($results->total() === 1 ? 'lekce' : ($results->total() >= 2 && $results->total() <= 4 ? 'lekce' : 'lekcí')) }}
                        @else
                            {{ 'Zobrazeno '.$results->total().' '.($results->total() === 1 ? 'termín' : ($results->total() >= 2 && $results->total() <= 4 ? 'termíny' : 'termínů')) }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    @endif

    @if($results->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface-alt px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
                <x-lucide name="calendar-x" class="h-6 w-6" />
            </span>
            @if($filtersActive)
                <p class="font-heading text-lg font-semibold text-neutral-900">
                    {{ $isEvents ? 'Žádné lekce neodpovídají zadaným filtrům.' : 'Žádné kurzy neodpovídají zadaným filtrům.' }}
                </p>
                <p class="max-w-md text-sm leading-relaxed text-neutral-600">Zkuste změnit kategorii, zrušit vyhledávání — nebo se nám ozvěte a poradíme vám s výběrem.</p>
            @elseif($isEvents)
                <p class="font-heading text-lg font-semibold text-neutral-900">
                    Právě nejsou vypsané žádné lekce.
                </p>
                <p class="max-w-md text-sm leading-relaxed text-neutral-600">Sledujte nás — nové termíny přidáváme průběžně. Rádi vám také dáme vědět e-mailem přes newsletter níže.</p>
            @else
                <p class="font-heading text-lg font-semibold text-neutral-900">
                    Zatím nemáme vypsaný žádný otevřený termín.
                </p>
                <p class="max-w-md text-sm leading-relaxed text-neutral-600">Nové kurzy právě chystáme. Nechte nám na sebe kontakt a dáme vám vědět, jakmile otevřeme přihlašování.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @if($isEvents)
                @foreach($results as $event)
                    <x-site.offer-card
                        :soonest="$loop->first && $results->onFirstPage()"
                        :url="$event->permalink()"
                        :image="Media::url($event->displayCardImage(), '400')"
                        :image-alt="$event->displayName()"
                        :category="$categories->isEmpty() ? null : $event->category?->name"
                        :state="$event->offerState()"
                        :title="$event->displayName()"
                        :description="$event->displayDescription()"
                        :date="$event->startsAt()->format('j. n. Y').' · '.$event->startsAt()->format('H:i')"
                        :taken="$event->takenSpots()"
                        :capacity="$event->capacity"
                        :price="number_format($event->price, 0, ',', ' ').' Kč'"
                        cta-label="Přihlásit se"
                    />
                @endforeach
            @else
                @foreach($results as $series)
                    <x-site.offer-card
                        :url="$series->course->permalink().'?termin='.$series->getKey()"
                        :image="Media::url($series->course->cardImage(), '400')"
                        :image-alt="$series->course->name"
                        :category="$series->course->category?->name"
                        :state="$series->offerState()"
                        :title="$series->course->name"
                        :subtitle="$series->name"
                        :description="$series->course->description"
                        :date="'od '.$series->start_date->format('j. n. Y')"
                        :schedule="$series->shortScheduleSummary()"
                        :taken="$series->takenSpots()"
                        :capacity="$series->capacity"
                        :price="number_format($series->currentPrice(), 0, ',', ' ').' Kč'"
                        cta-label="Přihlásit se"
                    />
                @endforeach
            @endif
        </div>

        {{ $results->onEachSide(1)->links('livewire.partials.pagination') }}
    @endif

    @if($preparing->isNotEmpty())
        <div class="flex flex-col gap-6 border-t border-line pt-10">
            <div class="flex flex-col gap-1">
                <h3 class="font-heading text-xl font-bold text-neutral-900">Připravované kurzy</h3>
                <p class="text-sm text-neutral-600">Tyto kurzy aktuálně nemají vypsaný termín — nechte nám na sebe e-mail a dáme vám vědět jako prvním.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($preparing as $course)
                    <x-site.offer-card
                        :url="$course->permalink()"
                        :image="Media::url($course->cardImage(), '400')"
                        :image-alt="$course->name"
                        :category="$course->category?->name"
                        :state="\App\Enums\OfferState::Preparing"
                        :title="$course->name"
                        :description="$course->description"
                        muted
                    />
                @endforeach
            </div>
        </div>
    @endif

    @if($past->isNotEmpty())
        <div class="flex flex-col gap-6 border-t border-line pt-10">
            <div class="flex flex-col gap-1">
                <h3 class="font-heading text-xl font-bold text-neutral-900">Proběhlé akce</h3>
                <p class="text-sm text-neutral-600">Tyto akce pravidelně pořádáme — aktuálně nemají vypsaný termín.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($past as $event)
                    <x-site.offer-card
                        :url="$event->permalink()"
                        :image="Media::url($event->displayCardImage(), '400')"
                        :image-alt="$event->displayName()"
                        :category="$categories->isEmpty() ? null : $event->category?->name"
                        :state="$event->offerState()"
                        :title="$event->displayName()"
                        :description="$event->displayDescription()"
                        :date="$event->startsAt()->format('j. n. Y')"
                        :taken="$event->takenSpots()"
                        :capacity="$event->capacity"
                        muted
                    />
                @endforeach
            </div>
        </div>
    @endif
</div>
