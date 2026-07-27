<x-filament-panels::page>
    @if ($total === 0 && $hidden === [])
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-success-500" />
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Nic nečeká na rozhodnutí.</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Až se něco objeví, najdete to tady.</p>
            </div>
        </x-filament::section>
    @endif

    @foreach ($groups as $group => $suggestions)
        <x-filament::section>
            <x-slot name="heading">{{ $groupLabels[$group] }}</x-slot>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($suggestions as $suggestion)
                    @include('filament.partials.suggestion-card', ['suggestion' => $suggestion])
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    @if ($hidden !== [])
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Skryté návrhy ({{ count($hidden) }})</x-slot>
            <x-slot name="description">Odložená rozhodnutí, která stále platí. Vrátit je můžete kdykoli.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($hidden as $entry)
                    @include('filament.partials.suggestion-card', [
                        'suggestion' => $entry['suggestion'],
                        'muted' => true,
                    ])
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
