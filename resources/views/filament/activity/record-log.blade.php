@php
    use App\Filament\Resources\ActivityLog\ActivityLogResource;
    use App\Support\ActivityLog\ActivityPresenter;
@endphp

<div class="flex flex-col gap-4">
    @if ($activities->isNotEmpty() || $filtersActive)
        <div
            x-data="{ showFilters: @js($panelFilterCount > 0) }"
            class="flex flex-col gap-3"
        >
            <div class="flex items-center gap-2">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="flex-1">
                    <x-filament::input
                        type="search"
                        placeholder="Hledat v historii…"
                        wire:model.live.debounce.400ms="search"
                    />
                </x-filament::input.wrapper>

                <x-filament::button
                    color="gray"
                    icon="heroicon-m-funnel"
                    :badge="$panelFilterCount ?: null"
                    badge-color="primary"
                    x-on:click="showFilters = ! showFilters"
                    x-bind:aria-expanded="showFilters ? 'true' : 'false'"
                >
                    Filtry
                </x-filament::button>
            </div>

            <div
                x-show="showFilters"
                x-collapse
                class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                {{ $this->filtersForm }}
            </div>

            @if ($filtersActive)
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($indicators as $indicator)
                        <x-filament::badge
                            tag="button"
                            color="gray"
                            icon="heroicon-m-x-mark"
                            icon-position="after"
                            wire:click="clearFilter('{{ $indicator['key'] }}')"
                            wire:key="indicator-{{ $indicator['key'] }}"
                        >
                            {{ $indicator['label'] }}
                        </x-filament::badge>
                    @endforeach

                    <x-filament::link tag="button" color="danger" size="sm" wire:click="resetFilters">
                        Zrušit vše
                    </x-filament::link>
                </div>
            @endif
        </div>
    @endif

    @if ($activities->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <x-filament::icon icon="heroicon-o-clock" class="h-8 w-8 text-gray-400" />
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $filtersActive ? 'Žádné záznamy neodpovídají filtru.' : 'Zatím žádná zaznamenaná aktivita.' }}
            </p>
        </div>
    @else
        <ul class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($activities as $activity)
                @php
                    $color = ActivityPresenter::eventColor($activity->event);
                @endphp
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span @class([
                            'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                            'bg-success-500' => $color === 'success',
                            'bg-warning-500' => $color === 'warning',
                            'bg-danger-500' => $color === 'danger',
                            'bg-info-500' => $color === 'info',
                            'bg-gray-400' => $color === 'gray',
                        ])></span>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-700 dark:text-gray-200">{{ ActivityPresenter::summary($activity) }}</p>
                            <p class="text-xs text-gray-400">
                                {{ ActivityPresenter::causerLabel($activity) }}
                                · {{ $activity->created_at?->format('d.m.Y H:i') }}
                                · {{ $activity->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ ActivityLogResource::getUrl('view', ['record' => $activity]) }}" class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        Detail
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($activities->hasPages())
            <x-filament::pagination :paginator="$activities" />
        @endif
    @endif
</div>
