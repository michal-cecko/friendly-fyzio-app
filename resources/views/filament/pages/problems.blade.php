<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Konflikty</x-slot>
        <x-slot name="description">Rezervace, lekce a pracovní doba, které se překrývají ve stejné místnosti nebo u stejného člověka (nejbližší měsíc).</x-slot>

        @if (count($problems) === 0)
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-success-500" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Vše v pořádku, žádné problémy.</p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($problems as $problem)
                    @include('filament.partials.conflict-card', ['problem' => $problem])
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @if (count($expected) > 0)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Očekávané překryvy</x-slot>
            <x-slot name="description">Blokace uvnitř pracovní doby. Rezervační systém je klientům odečítá sám, jde jen o přehled.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($expected as $problem)
                    @include('filament.partials.conflict-card', ['problem' => $problem])
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
