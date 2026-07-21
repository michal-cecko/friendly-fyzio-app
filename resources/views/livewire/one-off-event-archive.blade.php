@php
    use App\Support\Media;
@endphp

<div class="flex flex-col gap-8">
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
            @foreach($categories as $eventCategory)
                <button
                    type="button"
                    wire:click="selectCategory(@js($eventCategory->slug))"
                    @class([
                        'rounded-full px-4.5 py-2 font-heading text-[13px] font-medium transition',
                        'bg-primary text-white' => $category === $eventCategory->slug,
                        'border border-line bg-white text-neutral-700 hover:border-primary hover:text-primary' => $category !== $eventCategory->slug,
                    ])
                >{{ $eventCategory->name }}</button>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        @if($showSearch)
            <label class="relative block w-full max-w-md">
                <x-lucide name="search" class="pointer-events-none absolute left-4 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-neutral-400" />
                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Hledat akci…"
                    class="w-full rounded-full border border-line bg-white py-3 pl-11 pr-5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                >
            </label>
        @endif

        <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
            <input type="checkbox" wire:model.live="availableOnly" class="h-4.5 w-4.5 rounded border-line text-primary focus:ring-primary/30">
            Jenom dostupné
        </label>

        <p class="text-[13px] text-neutral-500">
            Zobrazeno {{ $upcoming->total() }} {{ $upcoming->total() === 1 ? 'akce' : ($upcoming->total() >= 2 && $upcoming->total() <= 4 ? 'akce' : 'akcí') }}
        </p>
    </div>

    @if($upcoming->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface-alt px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
                <x-lucide name="calendar-x" class="h-6 w-6" />
            </span>
            <p class="font-heading text-lg font-semibold text-neutral-900">
                {{ filled($search) || $availableOnly || $category !== null ? 'Žádná akce neodpovídá zadaným filtrům.' : 'Právě nejsou vypsané žádné akce.' }}
            </p>
            <p class="max-w-md text-sm leading-relaxed text-neutral-600">Sledujte nás — nové termíny přidáváme průběžně. Rádi vám také dáme vědět e-mailem přes newsletter níže.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach($upcoming as $event)
                <x-site.offer-card
                    :url="$event->permalink()"
                    :image="Media::url($event->displayImage(), '400')"
                    :image-alt="$event->name"
                    :category="$fixedCategory === null ? $event->category?->name : null"
                    :state="$event->offerState()"
                    :title="$event->name"
                    :description="$event->displayDescription()"
                    :date="$event->startsAt()->format('j. n. Y').' · '.$event->startsAt()->format('H:i')"
                    :taken="$event->takenSpots()"
                    :capacity="$event->capacity"
                    :price="number_format($event->price, 0, ',', ' ').' Kč'"
                    cta-label="Přihlásit se"
                />
            @endforeach
        </div>

        {{ $upcoming->onEachSide(1)->links('livewire.partials.pagination') }}
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
                        :image="Media::url($event->displayImage(), '400')"
                        :image-alt="$event->name"
                        :category="$fixedCategory === null ? $event->category?->name : null"
                        :state="$event->offerState()"
                        :title="$event->name"
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
