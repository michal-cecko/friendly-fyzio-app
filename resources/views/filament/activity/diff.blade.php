@php
    use App\Support\ActivityLog\ActivityPresenter;

    /** @var \Spatie\Activitylog\Models\Activity $record */
    $record = $getRecord();
    $changes = $record->attribute_changes?->toArray() ?? [];
    $new = $changes['attributes'] ?? [];
    $old = $changes['old'] ?? [];

    $format = function ($value): string {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'ano' : 'ne';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    };

    // Update = before/after diff; create/delete = the full record snapshot.
    $isUpdate = $record->event === 'updated';
    $snapshot = $record->event === 'deleted' ? $old : $new;
    $rows = collect($isUpdate ? array_keys($new + $old) : array_keys($snapshot))
        ->reject(fn (string $key): bool => $key === 'updated_at')
        ->values();
@endphp

@if ($rows->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Bez zaznamenaných hodnot.</p>
@elseif ($isUpdate)
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-400 dark:border-white/10">
                    <th class="py-2 pe-4 font-medium">Pole</th>
                    <th class="py-2 pe-4 font-medium">Původní</th>
                    <th class="py-2 font-medium">Nová</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($rows as $key)
                    @php $changed = ($old[$key] ?? null) !== ($new[$key] ?? null); @endphp
                    <tr @class(['bg-warning-50/40 dark:bg-warning-400/5' => $changed])>
                        <td class="py-2 pe-4 align-top font-medium text-gray-700 dark:text-gray-200">{{ ActivityPresenter::attributeLabel($key) }}</td>
                        <td class="py-2 pe-4 align-top text-gray-500 line-through decoration-danger-400/60 dark:text-gray-400">{{ $format($old[$key] ?? null) }}</td>
                        <td class="py-2 align-top font-medium text-gray-800 dark:text-gray-100">{{ $format($new[$key] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
        {{ $record->event === 'deleted' ? 'Kompletní stav záznamu v okamžiku smazání:' : 'Hodnoty vytvořeného záznamu:' }}
    </p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($rows as $key)
                    <tr>
                        <td class="w-1/3 py-2 pe-4 align-top font-medium text-gray-700 dark:text-gray-200">{{ ActivityPresenter::attributeLabel($key) }}</td>
                        <td class="py-2 align-top text-gray-800 dark:text-gray-100 break-all">{{ $format($snapshot[$key] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
