@php
    use App\Filament\Resources\ActivityLog\ActivityLogResource;
    use App\Support\ActivityLog\ActivityPresenter;
@endphp

@if ($activities->isEmpty())
    <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
        <x-filament::icon icon="heroicon-o-clock" class="h-8 w-8 text-gray-400" />
        <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádná zaznamenaná aktivita.</p>
    </div>
@else
    <ul class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($activities as $activity)
            @php
                $changeCount = count($activity->attribute_changes['attributes'] ?? []);
                $color = ActivityPresenter::eventColor($activity->event);
            @endphp
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="flex items-start gap-3">
                    <span @class([
                        'mt-0.5 inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                        'bg-success-100 text-success-700 dark:bg-success-400/20 dark:text-success-300' => $color === 'success',
                        'bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-300' => $color === 'warning',
                        'bg-danger-100 text-danger-700 dark:bg-danger-400/20 dark:text-danger-300' => $color === 'danger',
                        'bg-info-100 text-info-700 dark:bg-info-400/20 dark:text-info-300' => $color === 'info',
                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $color === 'gray',
                    ])>{{ ActivityPresenter::eventLabel($activity->event) }}</span>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-700 dark:text-gray-200">
                            {{ ActivityPresenter::causerLabel($activity) }}
                            @if ($activity->event === 'updated' && $changeCount > 0)
                                <span class="text-gray-400">· {{ $changeCount }} {{ $changeCount === 1 ? 'změna' : ($changeCount < 5 ? 'změny' : 'změn') }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-400">{{ $activity->created_at?->format('d.m.Y H:i') }} · {{ $activity->created_at?->diffForHumans() }}</p>
                    </div>
                </div>
                <a href="{{ ActivityLogResource::getUrl('view', ['record' => $activity]) }}" class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Detail
                </a>
            </li>
        @endforeach
    </ul>
@endif
