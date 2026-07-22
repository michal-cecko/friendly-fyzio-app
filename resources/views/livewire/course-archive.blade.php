@php
    use App\Support\Media;
@endphp

<div class="flex flex-col gap-8">
    @if($showFilters || $showSearch)
        <div class="flex flex-col gap-4">
            @if($showSearch)
                <label class="relative block max-w-md">
                    <x-lucide name="search" class="pointer-events-none absolute left-4 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-neutral-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Hledat kurz…"
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
                        @foreach($categories as $courseCategory)
                            <button
                                type="button"
                                wire:click="selectCategory(@js($courseCategory->slug))"
                                @class([
                                    'rounded-full px-4.5 py-2 font-heading text-[13px] font-medium transition',
                                    'bg-primary text-white' => $category === $courseCategory->slug,
                                    'border border-line bg-white text-neutral-700 hover:border-primary hover:text-primary' => $category !== $courseCategory->slug,
                                ])
                            >{{ $courseCategory->name }}</button>
                        @endforeach
                    </div>

                    <p class="text-[13px] text-neutral-500">
                        {{ 'Zobrazeno '.$results->total().' '.($results->total() === 1 ? 'termín' : ($results->total() >= 2 && $results->total() <= 4 ? 'termíny' : 'termínů')) }}
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
                    Žádné kurzy neodpovídají zadaným filtrům.
                </p>
                <p class="max-w-md text-sm leading-relaxed text-neutral-600">Zkuste změnit kategorii, zrušit vyhledávání — nebo se nám ozvěte a poradíme vám s výběrem.</p>
            @else
                <p class="font-heading text-lg font-semibold text-neutral-900">
                    Zatím nemáme vypsaný žádný otevřený termín.
                </p>
                <p class="max-w-md text-sm leading-relaxed text-neutral-600">Nové kurzy právě chystáme. Nechte nám na sebe kontakt a dáme vám vědět, jakmile otevřeme přihlašování.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach($results as $series)
                <x-site.offer-card
                    :url="$series->course->permalink().'?termin='.$series->getKey()"
                    :image="Media::url($series->course->featured_image, '400')"
                    :image-alt="$series->course->name"
                    :category="$series->course->category?->name"
                    :state="$series->offerState()"
                    :title="$series->course->name"
                    :subtitle="$series->name"
                    :description="$series->course->description"
                    :date="'od '.$series->start_date->format('j. n. Y')"
                    :taken="$series->takenSpots()"
                    :capacity="$series->capacity"
                    :price="number_format($series->currentPrice(), 0, ',', ' ').' Kč'"
                    cta-label="Přihlásit se"
                />
            @endforeach
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
                        :image="Media::url($course->featured_image, '400')"
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
    @if($crossSell !== null)
        <div class="flex flex-col gap-6 rounded-2xl bg-primary-light/60 p-8 lg:p-10">
            <div class="flex flex-col gap-2">
                <h3 class="font-heading text-2xl font-bold text-neutral-900">{{ $crossSell['title'] }}</h3>
                <p class="max-w-2xl text-sm leading-relaxed text-neutral-700">{{ $crossSell['text'] }}</p>
            </div>

            @if($crossSell['events']->isNotEmpty())
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($crossSell['events'] as $event)
                        <a href="{{ $event->permalink() }}" class="group flex items-center justify-between gap-4 rounded-xl border border-line bg-white p-5 transition hover:border-primary">
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="truncate font-heading text-sm font-semibold text-neutral-900">{{ $event->name }}</span>
                                <span class="text-sm text-neutral-600">{{ $event->startsAt()->format('j. n. Y') }} · {{ $event->startsAt()->format('H:i') }} · {{ number_format($event->price, 0, ',', ' ') }} Kč</span>
                            </span>
                            <x-lucide name="chevron-right" class="h-5 w-5 shrink-0 text-primary transition group-hover:translate-x-0.5" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if($crossSell['url'] !== null)
                <a href="{{ $crossSell['url'] }}" class="inline-flex w-fit items-center justify-center gap-2.5 rounded-full bg-primary px-8 py-4 font-heading text-base font-semibold text-white transition hover:bg-primary-dark">
                    Všechny termíny
                    <x-lucide name="arrow-right" class="h-5 w-5" />
                </a>
            @endif
        </div>
    @endif
</div>
