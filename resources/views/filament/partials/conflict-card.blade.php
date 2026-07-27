@php
    use Illuminate\Support\Carbon;

    /** @var \App\Support\Reservations\Conflict $problem */
    $isSoft = ! $problem->isHard();
    $resolveUrl = $problem->a->url() ?? $problem->b->url();
@endphp

{{-- Laid out as a column so that side by side in a grid, where every card is
     stretched to the tallest in its row, the resolve link still sits on the
     bottom edge rather than floating in the middle. --}}
<div @class([
    'flex flex-col rounded-lg border-s-[3px] px-3.5 py-3',
    'border-danger-500 bg-danger-50/70 dark:bg-danger-400/10' => ! $isSoft,
    'border-warning-500 bg-warning-50/70 dark:bg-warning-400/10' => $isSoft,
])>
    <div class="flex items-start justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-filament::icon
                :icon="$isSoft ? 'heroicon-m-information-circle' : 'heroicon-m-exclamation-triangle'"
                @class([
                    'h-4 w-4 shrink-0',
                    'text-danger-500' => ! $isSoft,
                    'text-warning-500' => $isSoft,
                ])
            />
            {{ $problem->title }}
        </p>
        <span class="whitespace-nowrap text-xs text-gray-400">
            {{ Carbon::parse($problem->date)->format('d.m.') }}@if ($problem->occurrences > 1) <span class="font-medium">+{{ $problem->occurrences - 1 }}</span>@endif
        </span>
    </div>

    <p class="mt-1 ps-6 text-xs text-gray-500 dark:text-gray-400">
        {{ $problem->type === 'room' ? 'Místnost' : 'Terapeut' }}:
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $problem->shared }}</span>
        @if ($problem->occurrences > 1)
            · opakuje se {{ $problem->occurrences }}×
        @endif
    </p>

    <div class="mt-2 ps-6">
        @foreach ([$problem->a, $problem->b] as $side)
            @php $sideUrl = $side->url(); @endphp
            @if ($sideUrl)
                <a href="{{ $sideUrl }}" @class([
                    'block truncate text-sm text-gray-700 hover:underline dark:text-gray-200',
                    'hover:text-danger-600 dark:hover:text-danger-400' => ! $isSoft,
                    'hover:text-warning-600 dark:hover:text-warning-400' => $isSoft,
                ])>{{ $side->time }} · {{ $side->label }}</a>
            @else
                <span class="block truncate text-sm text-gray-700 dark:text-gray-200">{{ $side->time }} · {{ $side->label }}</span>
            @endif
        @endforeach
    </div>

    @if ($resolveUrl)
        <div class="mt-auto pt-2.5 ps-6">
            <a href="{{ $resolveUrl }}" @class([
                'inline-flex items-center gap-1 text-sm font-semibold hover:underline',
                'text-danger-600 hover:text-danger-700 dark:text-danger-400' => ! $isSoft,
                'text-warning-600 hover:text-warning-700 dark:text-warning-400' => $isSoft,
            ])>
                {{ $isSoft ? 'Zobrazit' : 'Vyřešit' }}
                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
            </a>
        </div>
    @endif
</div>
