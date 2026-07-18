@php
    use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
    use Illuminate\Support\Carbon;

    $describe = fn ($reservation): string => substr((string) $reservation->start_time, 0, 5)
        .'–'.substr((string) $reservation->end_time, 0, 5)
        .' · '.($reservation->client?->name ?? 'Rezervace');

    $isRoom = $problem['type'] === 'room';
    $shared = $isRoom
        ? ($problem['a']->room?->name ?? 'Místnost')
        : ($problem['a']->therapist?->user?->name ?? 'Terapeut');
    $resolveUrl = ReservationResource::getUrl('view', ['record' => $problem['a']]);
@endphp

<div class="rounded-lg border-s-[3px] border-danger-500 bg-danger-50/70 px-3.5 py-3 dark:bg-danger-400/10">
    <div class="flex items-start justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-danger-500" />
            {{ $isRoom ? 'Dvojí rezervace místnosti' : 'Dvojí rezervace terapeuta' }}
        </p>
        <span class="whitespace-nowrap text-xs text-gray-400">{{ Carbon::parse($problem['date'])->format('d.m.') }}</span>
    </div>

    <p class="mt-1 ps-6 text-xs text-gray-500 dark:text-gray-400">
        {{ $isRoom ? 'Místnost' : 'Terapeut' }}: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $shared }}</span>
    </p>

    <div class="mt-2 ps-6">
        <a href="{{ $resolveUrl }}" class="block truncate text-sm text-gray-700 hover:text-danger-600 hover:underline dark:text-gray-200 dark:hover:text-danger-400">{{ $describe($problem['a']) }}</a>
        <a href="{{ ReservationResource::getUrl('view', ['record' => $problem['b']]) }}" class="block truncate text-sm text-gray-700 hover:text-danger-600 hover:underline dark:text-gray-200 dark:hover:text-danger-400">{{ $describe($problem['b']) }}</a>
    </div>

    <div class="mt-2.5 ps-6">
        <a href="{{ $resolveUrl }}" class="inline-flex items-center gap-1 text-sm font-semibold text-danger-600 hover:text-danger-700 hover:underline dark:text-danger-400">
            Vyřešit
            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
        </a>
    </div>
</div>
