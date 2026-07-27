@php
    $percent = min(100, max(0, $occupancy['percent']));

    // Written out in full so Tailwind keeps these classes in the compiled theme.
    [$barClass, $labelClass] = match ($occupancy['tone']) {
        'comfortable' => ['bg-success-500', 'text-success-700 dark:text-success-400'],
        'filling' => ['bg-warning-500', 'text-warning-700 dark:text-warning-400'],
        'tight' => ['bg-danger-500', 'text-danger-700 dark:text-danger-400'],
        default => ['bg-gray-300 dark:bg-gray-600', 'text-gray-500 dark:text-gray-400'],
    };
@endphp

<div @class(['flex flex-col gap-1', $alignClass ?? 'items-start', $wrapperClass ?? ''])>
    <span class="text-[0.625rem] font-semibold tabular-nums {{ $labelClass }}">
        {{ $occupancy['free'] }}/{{ $occupancy['capacity'] }}
    </span>

    <span class="block h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" style="width: 5rem">
        <span class="block h-full rounded-full {{ $barClass }}" style="width: {{ $percent }}%"></span>
    </span>
</div>
