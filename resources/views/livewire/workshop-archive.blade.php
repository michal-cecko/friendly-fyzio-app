@php
    use App\Support\Media;
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        @if($showSearch)
            <label class="relative block w-full max-w-md">
                <x-lucide name="search" class="pointer-events-none absolute left-4 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-neutral-400" />
                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Hledat workshop…"
                    class="w-full rounded-full border border-line bg-white py-3 pl-11 pr-5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                >
            </label>
        @endif

        <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
            <input type="checkbox" wire:model.live="availableOnly" class="h-4.5 w-4.5 rounded border-line text-primary focus:ring-primary/30">
            Jenom dostupné
        </label>

        <p class="text-[13px] text-neutral-500">
            Zobrazeno {{ $upcoming->total() }} {{ $upcoming->total() === 1 ? 'workshop' : ($upcoming->total() >= 2 && $upcoming->total() <= 4 ? 'workshopy' : 'workshopů') }}
        </p>
    </div>

    @if($upcoming->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface-alt px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
                <x-lucide name="calendar-x" class="h-6 w-6" />
            </span>
            <p class="font-heading text-lg font-semibold text-neutral-900">
                {{ filled($search) || $availableOnly ? 'Žádný workshop neodpovídá zadaným filtrům.' : 'Právě nejsou vypsané žádné workshopy.' }}
            </p>
            <p class="max-w-md text-sm leading-relaxed text-neutral-600">Sledujte nás — nové termíny přidáváme průběžně. Rádi vám také dáme vědět e-mailem přes newsletter níže.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach($upcoming as $workshop)
                <x-site.offer-card
                    :url="$workshop->permalink()"
                    :image="Media::url($workshop->featured_image, '400')"
                    :image-alt="$workshop->name"
                    :state="$workshop->offerState()"
                    :title="$workshop->name"
                    :description="$workshop->description"
                    :date="$workshop->startsAt()->format('j. n. Y').' · '.$workshop->startsAt()->format('H:i')"
                    :taken="$workshop->takenSpots()"
                    :capacity="$workshop->capacity"
                    :price="number_format($workshop->price, 0, ',', ' ').' Kč'"
                    cta-label="Registrovat"
                />
            @endforeach
        </div>

        {{ $upcoming->onEachSide(1)->links('livewire.partials.pagination') }}
    @endif

    @if($past->isNotEmpty())
        <div class="flex flex-col gap-6 border-t border-line pt-10">
            <div class="flex flex-col gap-1">
                <h3 class="font-heading text-xl font-bold text-neutral-900">Proběhlé workshopy</h3>
                <p class="text-sm text-neutral-600">Tyto workshopy pravidelně pořádáme — aktuálně nemají vypsaný termín.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($past as $workshop)
                    <x-site.offer-card
                        :url="$workshop->permalink()"
                        :image="Media::url($workshop->featured_image, '400')"
                        :image-alt="$workshop->name"
                        :state="$workshop->offerState()"
                        :title="$workshop->name"
                        :description="$workshop->description"
                        :date="$workshop->startsAt()->format('j. n. Y')"
                        :taken="$workshop->takenSpots()"
                        :capacity="$workshop->capacity"
                        muted
                    />
                @endforeach
            </div>
        </div>
    @endif
</div>
