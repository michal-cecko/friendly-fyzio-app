@php
    // Tone classes are written out in full, never interpolated: Tailwind's
    // scanner reads this file as source, so a constructed class name would
    // simply never make it into the compiled theme.
    [$border, $background, $iconColor, $ctaColor] = match ($suggestion['tone']) {
        'danger' => ['border-danger-500', 'bg-danger-50/70 dark:bg-danger-400/10', 'text-danger-500', 'text-danger-600 hover:text-danger-700 dark:text-danger-400'],
        'success' => ['border-success-500', 'bg-success-50/70 dark:bg-success-400/10', 'text-success-500', 'text-success-600 hover:text-success-700 dark:text-success-400'],
        'info' => ['border-info-500', 'bg-info-50/70 dark:bg-info-400/10', 'text-info-500', 'text-info-600 hover:text-info-700 dark:text-info-400'],
        default => ['border-warning-500', 'bg-warning-50/70 dark:bg-warning-400/10', 'text-warning-500', 'text-warning-600 hover:text-warning-700 dark:text-warning-400'],
    };

    $muted = $muted ?? false;

    $dismissArguments = [
        'key' => $suggestion['key'],
        'type' => $suggestion['type'],
        'fingerprint' => $suggestion['fingerprint'],
        'snooze' => $suggestion['snoozeOnDismiss'],
    ];
@endphp

<div @class([
    'rounded-lg border-s-[3px] px-3.5 py-3',
    $border,
    $background,
    'opacity-60' => $muted,
])>
    <div class="flex items-start justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-filament::icon :icon="$suggestion['icon']" @class(['h-4 w-4 shrink-0', $iconColor]) />
            {{ $suggestion['title'] }}
        </p>

        @if ($suggestion['meta'])
            <span class="whitespace-nowrap text-xs text-gray-400">{{ $suggestion['meta'] }}</span>
        @endif
    </div>

    <p class="mt-1 ps-6 text-xs text-gray-500 dark:text-gray-400">{{ $suggestion['detail'] }}</p>

    <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 ps-6">
        <a href="{{ $suggestion['url'] }}" @class(['inline-flex items-center gap-1 text-sm font-semibold hover:underline', $ctaColor])>
            {{ $suggestion['cta'] }}
            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
        </a>

        @if (! $muted && $suggestion['resolveLabel'])
            <button
                type="button"
                wire:click="mountAction('resolveSuggestion', @js([
                    'type' => $suggestion['type'],
                    'id' => $suggestion['id'],
                    'label' => $suggestion['resolveLabel'],
                    'confirm' => $suggestion['resolveConfirm'],
                ]))"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 hover:underline dark:text-gray-200"
            >
                <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                {{ $suggestion['resolveLabel'] }}
            </button>
        @endif

        @if ($muted)
            <button
                type="button"
                wire:click="mountAction('restoreSuggestion', @js(['key' => $suggestion['key']]))"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:underline dark:text-gray-400"
            >
                <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                Vrátit
            </button>
        @else
            <button
                type="button"
                wire:click="mountAction('dismissSuggestion', @js($dismissArguments))"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-gray-600 hover:underline dark:hover:text-gray-300"
            >
                <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4" />
                {{ $suggestion['dismissLabel'] }}
            </button>
        @endif
    </div>
</div>
