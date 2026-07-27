<x-filament-panels::page>
    @php
        $query = trim($q);
        $groups = filled($query) ? $this->results : collect();
        $totalResults = $groups->sum(fn ($group) => count($group->results));
    @endphp

    <div class="mx-auto w-full max-w-4xl space-y-6">
        {{-- Search input --}}
        <div class="space-y-3">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" class="shadow-sm">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.500ms="q"
                    placeholder="Hledejte klienty, rezervace, kurzy, platby…"
                    autofocus
                    class="!py-3 !text-base"
                />

                <x-slot name="suffix">
                    <x-filament::loading-indicator
                        wire:loading.delay
                        wire:target="q, includeTrashed"
                        class="h-5 w-5 text-gray-400"
                    />
                </x-slot>
            </x-filament::input.wrapper>

            <div class="flex items-center gap-3">
                <x-filament::toggle state="$wire.entangle('includeTrashed').live" />

                <button
                    type="button"
                    class="text-start"
                    wire:click="$toggle('includeTrashed')"
                >
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Zahrnout smazané záznamy</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Smazané výsledky jsou označené červeným štítkem</span>
                </button>
            </div>
        </div>

        @if (blank($query))
            {{-- Recent searches / intro --}}
            @if (count($this->recentSearches))
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Nedávná hledání
                        </h2>

                        <x-filament::link tag="button" wire:click="clearRecentSearches" color="gray" size="sm">
                            Vymazat vše
                        </x-filament::link>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->recentSearches as $term)
                            <span
                                class="group inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white py-1.5 pe-2 ps-3 text-sm text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-primary-500/10"
                                wire:key="recent-{{ md5($term) }}"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    wire:click="searchFor({{ Js::from($term) }})"
                                >
                                    <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4 text-gray-400 group-hover:text-primary-500" />
                                    {{ $term }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-full p-0.5 text-gray-400 transition hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-500/10"
                                    title="Odebrat z historie"
                                    wire:click="forgetRecentSearch({{ Js::from($term) }})"
                                >
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @else
                <x-filament::empty-state
                    icon="heroicon-o-magnifying-glass"
                    heading="Prohledejte celou administraci"
                    description="Klienti, rezervace, kurzy, workshopy, platby, stránky i e-maily — včetně smazaných záznamů."
                />
            @endif
        @elseif ($totalResults === 0)
            @if (mb_strlen($query) >= \App\Filament\Support\Search\AdminSearchService::MINIMUM_QUERY_LENGTH)
                <x-filament::empty-state
                    icon="heroicon-o-face-frown"
                    heading="Žádné výsledky"
                    :description="'Pro dotaz „' . $query . '“ jsme nic nenašli. Zkuste jiné klíčové slovo' . ($includeTrashed ? '.' : ', nebo zapněte hledání ve smazaných záznamech.')"
                />
            @endif
        @else
            <div
                class="space-y-4"
                wire:key="results-{{ md5($query.($includeTrashed ? '1' : '0')) }}"
                x-data="{ activeGroup: null }"
            >
                {{-- Summary + category filter chips --}}
                <div class="space-y-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ trans_choice('{1} :count výsledek|[2,4] :count výsledky|[5,*] :count výsledků', $totalResults) }}
                        {{ trans_choice('{1}v :count kategorii|[2,*]v :count kategoriích', count($groups)) }}
                    </p>

                    @if (count($groups) > 1)
                        <div class="flex flex-wrap gap-2">
                            @foreach ($groups as $group)
                                <button
                                    type="button"
                                    wire:key="chip-{{ md5($group->label) }}"
                                    x-on:click="activeGroup = activeGroup === {{ Js::from($group->label) }} ? null : {{ Js::from($group->label) }}"
                                    x-bind:class="activeGroup === {{ Js::from($group->label) }}
                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium shadow-sm transition"
                                >
                                    @if ($group->icon)
                                        <x-filament::icon :icon="$group->icon" class="h-4 w-4" />
                                    @endif

                                    {{ $group->label }}

                                    <span class="rounded-full bg-gray-100 px-1.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        {{ count($group->results) }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Result groups --}}
                @foreach ($groups as $group)
                    <section
                        wire:key="group-{{ md5($group->label) }}"
                        x-show="activeGroup === null || activeGroup === {{ Js::from($group->label) }}"
                        class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                            @if ($group->icon)
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                    <x-filament::icon :icon="$group->icon" class="h-5 w-5" />
                                </span>
                            @endif

                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $group->label }}
                            </h2>

                            <x-filament::badge color="gray">{{ count($group->results) }}</x-filament::badge>
                        </header>

                        <ul class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($group->results as $result)
                                <li>
                                    @if ($result->url)
                                        <a
                                            href="{{ $result->url }}"
                                            wire:navigate
                                            class="group flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-white/5"
                                        >
                                    @else
                                        <div class="flex items-center gap-3 px-4 py-3">
                                    @endif
                                            <div @class(['min-w-0 flex-1', 'opacity-60' => $result->isTrashed])>
                                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $result->titleHtml }}
                                                </p>

                                                @if (count($result->detailsHtml))
                                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                                        @foreach ($result->detailsHtml as $detailLabel => $detailValue)
                                                            @if (! $loop->first)
                                                                <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
                                                            @endif
                                                            <span>{{ $detailLabel }}: {{ $detailValue }}</span>
                                                        @endforeach
                                                    </p>
                                                @endif
                                            </div>

                                            @if ($result->isTrashed)
                                                <x-filament::badge color="danger" class="shrink-0">Smazáno</x-filament::badge>
                                            @endif

                                            @if ($result->url)
                                                <x-filament::icon
                                                    icon="heroicon-m-chevron-right"
                                                    class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-primary-500 dark:text-gray-600"
                                                />
                                            @endif
                                    @if ($result->url)
                                        </a>
                                    @else
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
