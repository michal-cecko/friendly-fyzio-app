<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Konflikty rezervací</x-slot>
        <x-slot name="description">Překrývající se rezervace ve stejné místnosti nebo u stejného terapeuta (nejbližší měsíc).</x-slot>

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
</x-filament-panels::page>
