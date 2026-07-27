<x-filament-widgets::widget class="h-full [&>.fi-section]:h-full [&_.fi-section-content-ctn]:h-full">
    <x-filament::section class="flex h-full flex-col">
        <x-slot name="heading">Návrhy</x-slot>

        @if ($total === 0)
            <div class="flex h-full flex-1 flex-col items-center justify-center gap-2 py-8 text-center">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-9 w-9 text-success-500" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Nic nečeká na rozhodnutí.</p>
            </div>
        @else
            <div class="flex flex-col gap-2.5">
                @foreach ($suggestions as $suggestion)
                    @include('filament.partials.suggestion-card', ['suggestion' => $suggestion])
                @endforeach

                @if ($total > count($suggestions))
                    <a href="{{ \App\Filament\Pages\Suggestions::getUrl() }}" class="inline-flex items-center justify-center gap-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
                        Zobrazit všech {{ $total }} návrhů
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
