<x-filament-panels::page>
    @php
        $sections = $this->sections;
        $topic = $this->current;
        $query = trim($q);
        $results = filled($query) ? $this->results : collect();
    @endphp

    @if ($archived = $this->archived)
        {{-- Reading an archive is easy to forget you are doing; say so above the article. --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
            <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 shrink-0" />

            <span>
                Prohlížíte archivní verzi z <strong>{{ $archived->label() }}</strong>@if ($archived->commitLabel()) <span class="text-warning-700/80 dark:text-warning-300/70">({{ $archived->commitLabel() }})</span>@endif. Popisuje panel tak, jak vypadal tehdy.
            </span>

            <a
                href="{{ \App\Filament\Pages\Help::getUrl(['version' => \App\Filament\Support\Help\HelpVersions::LATEST]) }}"
                class="font-semibold underline underline-offset-2"
            >
                Zpět na aktuální
            </a>
        </div>
    @endif

    @if ($sections->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-book-open"
            heading="Nápověda zatím není k dispozici"
            description="Články nápovědy nejsou nainstalované."
        />
    @else
        <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            {{-- Search + topic tree --}}
            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" class="shadow-sm">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="q"
                        placeholder="Hledat v nápovědě…"
                    />

                    <x-slot name="suffix">
                        <x-filament::loading-indicator
                            wire:loading.delay
                            wire:target="q"
                            class="h-5 w-5 text-gray-400"
                        />
                    </x-slot>
                </x-filament::input.wrapper>

                @if (filled($query))
                    {{-- Results replace the tree while searching --}}
                    @php
                        // Spelled out rather than trans_choice(): Laravel's selector
                        // resolves the Czech locale to the two-form rule, so five
                        // results come out as "výsledky" instead of "výsledků".
                        $count = count($results);
                        $noun = match (true) {
                            $count === 1 => 'výsledek',
                            $count >= 2 && $count <= 4 => 'výsledky',
                            default => 'výsledků',
                        };
                    @endphp

                    <div class="space-y-2">
                        @if ($count)
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $count }} {{ $noun }}
                            </p>
                        @endif

                        @forelse ($results as $result)
                            <button
                                type="button"
                                wire:key="result-{{ $result->topic->id }}"
                                wire:click="openTopic({{ Js::from($result->topic->id) }})"
                                class="block w-full rounded-lg border border-gray-200 bg-white p-3 text-start shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-primary-500/10"
                            >
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $result->topic->sectionTitle }}</span>
                                <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $result->titleHtml }}</span>
                                <span class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $result->excerptHtml }}</span>
                            </button>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                Nic jsme nenašli. Zkuste jiné slovo — třeba „rezervace", „faktura" nebo „kurz".
                            </p>
                        @endforelse
                    </div>
                @else
                    <nav class="space-y-5" aria-label="Obsah nápovědy">
                        @foreach ($sections as $section)
                            <div wire:key="section-{{ $section->id }}">
                                <p class="mb-1.5 flex items-center gap-1.5 px-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    @if ($section->icon)
                                        <x-filament::icon :icon="$section->icon" class="h-4 w-4" />
                                    @endif

                                    {{ $section->title }}
                                </p>

                                <ul class="space-y-0.5">
                                    @foreach ($section->topics as $item)
                                        @php $isActive = $topic?->id === $item->id; @endphp

                                        <li wire:key="topic-{{ $item->id }}">
                                            <button
                                                type="button"
                                                wire:click="openTopic({{ Js::from($item->id) }})"
                                                @class([
                                                    'block w-full rounded-lg px-2 py-1.5 text-start text-sm transition',
                                                    'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $isActive,
                                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $isActive,
                                                ])
                                                @if ($isActive) aria-current="page" @endif
                                            >
                                                {{ $item->title }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </nav>
                @endif
            </aside>

            {{-- Article --}}
            <div>
                @if ($topic)
                    <x-filament::section>
                        <x-slot name="heading">{{ $topic->title }}</x-slot>
                        <x-slot name="description">{{ $topic->sectionTitle }}</x-slot>

                        <article class="ff-help-prose">
                            {!! $topic->html() !!}
                        </article>
                    </x-filament::section>
                @else
                    <x-filament::empty-state
                        icon="heroicon-o-book-open"
                        heading="Vyberte téma"
                        description="Zvolte článek v seznamu vlevo nebo použijte hledání."
                    />
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
